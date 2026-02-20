<?php

namespace App\Services;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SoapClient;
use Exception;

class AfipService
{
    // === CONFIGURACIÓN ===
    private static $CUIT = 30633784104; 
    private static $PV   = 10; 
    
    // Rutas
    private static $baseDir;
    private static $cert;
    private static $key;
    private static $wsdl;
    private static $xmlFolder;

    /**
     * Inicializa rutas
     */
    private static function init()
    {
        self::$baseDir = storage_path('app/afip/'); 
        
        self::$cert      = self::$baseDir . 'cert';
        self::$key       = self::$baseDir . 'key';
        self::$wsdl      = self::$baseDir . 'wsfev1.wsdl';
        self::$xmlFolder = self::$baseDir . 'xml/';

        if (!file_exists(self::$xmlFolder)) mkdir(self::$xmlFolder, 0775, true);
        
        // Si el WSDL no existe ni siquiera, lo bajamos ya.
        if (!file_exists(self::$wsdl)) {
            self::forceUpdateWsdl();
        }
    }

    /**
     * Contexto SSL "Relajado" (SECLEVEL=1)
     */
    private static function getContext()
    {
        return stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'ciphers' => 'DEFAULT@SECLEVEL=1' 
            ]
        ]);
    }

    /**
     * OBTIENE EL CLIENTE SOAP CON AUTO-REPARACIÓN
     * Si falla el WSDL local, lo descarga de nuevo y reintenta.
     */
    private static function getWsfeClient()
    {
        try {
            // Intento 1: Usar lo que tenemos
            return new SoapClient("file://" . self::$wsdl, [
                'soap_version' => SOAP_1_2,
                'stream_context' => self::getContext(),
                'exceptions' => true
            ]);
        } catch (Exception $e) {
            Log::warning("AFIP: Falló el WSDL local. Intentando actualizar y reintentar... Error: " . $e->getMessage());
            
            // Intento 2: Descargar WSDL fresco y probar de nuevo
            try {
                self::forceUpdateWsdl(); // Descarga forzada
                
                return new SoapClient("file://" . self::$wsdl, [
                    'soap_version' => SOAP_1_2,
                    'stream_context' => self::getContext(),
                    'exceptions' => true
                ]);
            } catch (Exception $e2) {
                // Si falla de nuevo, ya es un problema serio de red o de AFIP
                throw new Exception("Error crítico AFIP: No se pudo conectar ni actualizando el WSDL. " . $e2->getMessage());
            }
        }
    }

    /**
     * DESCARGA EL WSDL A LA FUERZA (Lógica de bajar_mapa.php)
     */
    private static function forceUpdateWsdl()
    {
        $url = "https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL";
        
        // Usamos el contexto inseguro para poder descargar desde AFIP
        $contenido = file_get_contents($url, false, self::getContext());

        if ($contenido && strlen($contenido) > 100) {
            file_put_contents(self::$wsdl, $contenido);
            Log::info("AFIP: WSDL actualizado correctamente.");
        } else {
            throw new Exception("No se pudo descargar el WSDL desde AFIP.");
        }
    }

   /**
     * Método principal para Facturar
     */
    public static function facturar(Order $record, array $data)
    {
        try {
            self::init();
            $auth = self::getAuth();
            $wsfe = self::getWsfeClient();

            $tipo_cbte = 6; // Factura B
            
            $ultimo = $wsfe->FECompUltimoAutorizado([
                'Auth' => $auth, 'PtoVta' => self::$PV, 'CbteTipo' => $tipo_cbte
            ]);
            $next = $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;

            // --- Lógica de totales original del usuario ---
            $total = round($record->total_amount, 2);
            if ($total <= 0) {
                $total = round($record->items->sum(fn($i) => $i->packed_quantity * $i->unit_price), 2);
            }

            if ($total <= 0) throw new Exception("El pedido no tiene un monto válido para facturar.");

            $neto  = round($total / 1.21, 2);
            $iva   = round($total - $neto, 2);

            if ($iva <= 0) {
                $alicIvaId = 3; 
                $baseImp   = $total; 
                $iva       = 0;
            } else {
                $alicIvaId = 5; 
                $baseImp   = $neto;
            }

            $req = [
                'Auth' => $auth,
                'FeCAEReq' => [
                    'FeCabReq' => ['CantReg' => 1, 'PtoVta' => self::$PV, 'CbteTipo' => $tipo_cbte],
                    'FeDetReq' => [
                        'FECAEDetRequest' => [
                            [
                                'Concepto' => 1, 'DocTipo' => 99, 'DocNro' => 0,
                                'CbteDesde' => $next, 'CbteHasta' => $next, 'CbteFch' => date('Ymd'),
                                'ImpTotal' => $total, 'ImpTotConc' => 0, 'ImpNeto' => $baseImp,
                                'ImpOpEx' => 0, 'ImpIVA' => $iva, 'ImpTrib' => 0, 'MonId' => 'PES', 'MonCotiz' => 1,
                                'Iva' => [
                                    'AlicIva' => [
                                        ['Id' => $alicIvaId, 'BaseImp' => $baseImp, 'Importe' => $iva]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $res = $wsfe->FECAESolicitar($req);

            if ($res->FECAESolicitarResult->FeCabResp->Resultado == 'A') {
                $detResponse = $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse;
                $item = is_array($detResponse) ? $detResponse[0] : $detResponse;
                $cae = $item->CAE;
                $vto = $item->CAEFchVto;

                DB::transaction(function () use ($record, $cae, $total, $next, $vto) {
                    // CAMBIO: invoices() en plural
                    $record->invoices()->create([
                        'invoice_type' => 'B',
                        'total_fiscal' => $total,
                        'cae_afip'     => $cae,
                        'cae_expiry'   => \Illuminate\Support\Carbon::createFromFormat('Ymd', $vto)->format('Y-m-d'),
                        'number'       => str_pad(self::$PV, 5, '0', STR_PAD_LEFT) . '-' . str_pad($next, 8, '0', STR_PAD_LEFT),
                    ]);

                    // COBRO EN CUENTA CORRIENTE
                    $record->client->increment('fiscal_debt', $total);
                    $record->update(['status' => OrderStatus::Checked]);
                });
                return ['success' => true, 'message' => "Factura B aprobada"];
            } else {
                $err = $res->FECAESolicitarResult->Errors->Err->Msg 
                       ?? $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs->Msg 
                       ?? 'Error desconocido';
                return ['success' => false, 'error' => "Rechazo AFIP: " . $err];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => "Error: " . $e->getMessage()];
        }
    }

    /**
     * Método para Anular
     */
    public static function anular(Order $order)
    {
        try {
            self::init();
            $facturaOriginal = $order->invoices()->where('invoice_type', 'B')->latest()->first();            
            
            if (!$facturaOriginal || empty($facturaOriginal->number)) {
                return ['success' => false, 'error' => "Falta el número de factura original en la BD."];
            }

            $partes = explode('-', $facturaOriginal->number);
            $nroOriginal = (int) end($partes);

            $auth = self::getAuth();
            $wsfe = self::getWsfeClient();
            $tipo_nc = 8; 
            $ultimo = $wsfe->FECompUltimoAutorizado(['Auth' => $auth, 'PtoVta' => self::$PV, 'CbteTipo' => $tipo_nc]);
            $nextNC = $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;

            $total = abs($facturaOriginal->total_fiscal);
            $neto  = round($total / 1.21, 2);
            $iva   = round($total - $neto, 2);

            $req = [
                'Auth' => $auth,
                'FeCAEReq' => [
                    'FeCabReq' => ['CantReg' => 1, 'PtoVta' => self::$PV, 'CbteTipo' => $tipo_nc],
                    'FeDetReq' => [
                        'FECAEDetRequest' => [
                            [
                                'Concepto' => 1, 'DocTipo' => 99, 'DocNro' => 0,
                                'CbteDesde' => $nextNC, 'CbteHasta' => $nextNC, 'CbteFch' => date('Ymd'),
                                'ImpTotal' => $total, 'ImpTotConc' => 0, 'ImpNeto' => ($iva > 0 ? $neto : $total), 
                                'ImpOpEx' => 0, 'ImpIVA' => $iva, 'ImpTrib' => 0, 'MonId' => 'PES', 'MonCotiz' => 1,
                                'Iva' => [
                                    'AlicIva' => [['Id' => ($iva > 0 ? 5 : 3), 'BaseImp' => ($iva > 0 ? $neto : $total), 'Importe' => $iva]]
                                ],
                                'CbtesAsoc' => [
                                    'CbteAsoc' => [['Tipo' => 6, 'PtoVta' => self::$PV, 'Nro' => $nroOriginal]]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $res = $wsfe->FECAESolicitar($req);

            if ($res->FECAESolicitarResult->FeCabResp->Resultado == 'A') {
                $detResponse = $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse;
                $item = is_array($detResponse) ? $detResponse[0] : $detResponse;
                
                $cae = $item->CAE;
                $vto = $item->CAEFchVto;

                DB::transaction(function () use ($order, $cae, $total, $nextNC, $vto, $facturaOriginal) {
                    // CAMBIO: invoices() en plural
                    $order->invoices()->create([
                        'invoice_type' => 'NC',
                        'total_fiscal' => -$total, 
                        'cae_afip'     => $cae,
                        'cae_expiry'   => \Illuminate\Support\Carbon::createFromFormat('Ymd', $vto)->format('Y-m-d'),
                        'number'       => str_pad(self::$PV, 5, '0', STR_PAD_LEFT) . '-' . str_pad($nextNC, 8, '0', STR_PAD_LEFT),
                        'parent_id'    => $facturaOriginal->id
                    ]);

                    // RESTAR DE CUENTA CORRIENTE
                    $order->client->decrement('fiscal_debt', $total);
                    $order->update(['status' => OrderStatus::Assembled]);
                });
                return ['success' => true, 'message' => "Anulación aprobada"];
            } else {
                $err = $res->FECAESolicitarResult->Errors->Err->Msg ?? 'Error AFIP';
                return ['success' => false, 'error' => "Rechazo AFIP: " . $err];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => "Error: " . $e->getMessage()];
        }
    }

    private static function getAuth()
    {
        $tokenFile = self::$xmlFolder . 'TOKEN_CACHE.json';

        if (file_exists($tokenFile)) {
            $cache = json_decode(file_get_contents($tokenFile), true);
            if (isset($cache['expiration']) && time() < $cache['expiration']) {
                return $cache['auth'];
            }
        }

        $tra = "<?xml version='1.0' encoding='UTF-8'?><loginTicketRequest version='1.0'>
    <header>
        <uniqueId>".time()."</uniqueId>
        <generationTime>".date('c',time()-600)."</generationTime>
        <expirationTime>".date('c',time()+600)."</expirationTime>
    </header>
    <service>wsfe</service>
</loginTicketRequest>";
$traFile = self::$xmlFolder . 'TRA.xml';
$cmsFile = self::$xmlFolder . 'TRA.tmp';

file_put_contents($traFile, $tra);

$status = openssl_pkcs7_sign($traFile, $cmsFile, "file://".self::$cert, ["file://".self::$key, ""], [], PKCS7_BINARY);
if (!$status) throw new Exception("Error firmando localmente.");

$inf = fopen($cmsFile, "r"); $cms = ""; $i = 0;
while (!feof($inf)) { $buffer = fgets($inf); if ($i++ >= 4 && trim($buffer)!="" && strpos($buffer,'Content-')===false &&
strpos($buffer,'--')===false) $cms .= trim($buffer); }
fclose($inf); $cms = preg_replace('/\s+/', '', $cms);

$wsaa = new SoapClient("https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL", [
'soap_version' => SOAP_1_2,
'stream_context' => self::getContext(),
'exceptions' => true
]);

$loginRes = $wsaa->loginCms(['in0' => $cms]);
$authXml = simplexml_load_string($loginRes->loginCmsReturn);

$auth = [
'Token' => (string)$authXml->credentials->token,
'Sign' => (string)$authXml->credentials->sign,
'Cuit' => self::$CUIT
];

file_put_contents($tokenFile, json_encode(['auth' => $auth, 'expiration' => time() + 36000]));

return $auth;
}
}