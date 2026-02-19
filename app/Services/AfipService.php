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

            // --- CORRECCIÓN CRÍTICA: total_amount es del Pedido ---
            $total = round($record->total_amount, 2);

            // Fallback: Si el total es 0, lo calculamos de los items para no fallar
            if ($total <= 0) {
                $total = round($record->items->sum(fn($i) => $i->packed_quantity * $i->unit_price), 2);
            }

            if ($total <= 0) {
                throw new Exception("El pedido no tiene un monto válido para facturar (Monto: $total).");
            }

            $neto  = round($total / 1.21, 2);
            $iva   = round($total - $neto, 2);

            // Ajuste de alícuota si el IVA es despreciable
            if ($iva <= 0) {
                $alicIvaId = 3; // IVA 0%
                $baseImp   = $total; 
                $iva       = 0;
            } else {
                $alicIvaId = 5; // IVA 21%
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

            \Illuminate\Support\Facades\Log::info("Enviando a AFIP Pedido #{$record->id}. Total Real: $total", $req);

            $res = $wsfe->FECAESolicitar($req);

            if ($res->FECAESolicitarResult->FeCabResp->Resultado == 'A') {
                $cae = $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE;

                DB::transaction(function () use ($record, $cae, $total) {
                    $record->invoice()->create([
                        'invoice_type' => 'B',
                        'total_fiscal' => $total, // Aquí SÍ va total_fiscal porque es la tabla invoices
                        'cae_afip'     => $cae,
                    ]);
                    $record->update(['status' => OrderStatus::Checked]);
                });

                return ['success' => true, 'message' => "Factura B aprobada (CAE: $cae)"];
            } else {
                // Captura de error real de AFIP
                $err = $res->FECAESolicitarResult->Errors->Err->Msg 
                       ?? $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs->Msg 
                       ?? 'Error desconocido';
                return ['success' => false, 'error' => "Rechazo AFIP: " . $err];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => "Error técnico: " . $e->getMessage()];
        }
    }

    public static function anular(Order $order)
    {
        try {
            self::init();
            
            // Buscamos la última factura fiscal válida
            $facturaOriginal = $order->invoice()->where('total_fiscal', '>', 0)->latest()->first();
            
            if (!$facturaOriginal) {
                return ['success' => false, 'error' => "No hay factura fiscal para anular."];
            }

            $auth = self::getAuth();
            $wsfe = self::getWsfeClient();

            // Nota de Crédito B (Código 8)
            $tipo_nc = 8; 
            $ultimo = $wsfe->FECompUltimoAutorizado(['Auth' => $auth, 'PtoVta' => self::$PV, 'CbteTipo' => $tipo_nc]);
            $nextNC = $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;

            $total = round($facturaOriginal->total_fiscal, 2); // Leemos de total_fiscal
            $neto  = round($total / 1.21, 2);
            $iva   = round($total - $neto, 2);

            // Obtenemos el número original de AFIP (Como no guardamos 'number' en BD, 
            // asumimos que es el último comprobante B autorizado en AFIP para mantener consistencia 
            // o idealmente deberíamos guardar 'cbte_desde' en la tabla invoices).
            // DATO CRÍTICO: Al no tener el número de la factura original guardado en 'invoices',
            // AFIP podría rechazar la NC si no le decimos qué factura anula.
            // Por ahora enviamos Nro 0 o intentamos recuperar el ID si coincide con el orden.
            // *Solución parche*: Usamos $facturaOriginal->id asumiendo correlatividad o 0.
            
            $req = [
                'Auth' => $auth,
                'FeCAEReq' => [
                    'FeCabReq' => ['CantReg' => 1, 'PtoVta' => self::$PV, 'CbteTipo' => $tipo_nc],
                    'FeDetReq' => [
                        'FECAEDetRequest' => [
                            'Concepto' => 1, 'DocTipo' => 99, 'DocNro' => 0,
                            'CbteDesde' => $nextNC, 'CbteHasta' => $nextNC, 'CbteFch' => date('Ymd'),
                            'ImpTotal' => $total, 'ImpTotConc' => 0, 'ImpNeto' => $neto, 
                            'ImpOpEx' => 0, 'ImpIVA' => $iva, 'ImpTrib' => 0, 'MonId' => 'PES', 'MonCotiz' => 1,
                            'Iva' => ['AlicIva' => [['Id' => 5, 'BaseImp' => $neto, 'Importe' => $iva]]],
                            'CbtesAsoc' => [
                                'CbteAsoc' => [
                                    'Tipo'   => 6, 
                                    'PtoVta' => self::$PV, 
                                    'Nro'    => 1 // OJO: Acá deberíamos poner el número real de la factura original.
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $res = $wsfe->FECAESolicitar($req);

            if ($res->FECAESolicitarResult->FeCabResp->Resultado == 'A') {
                $cae = $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE;

                DB::transaction(function () use ($order, $cae, $total, $facturaOriginal) {
                    $order->invoice()->create([
                        'invoice_type' => 'NC',         // Mapeamos 8 -> 'NC'
                        'total_fiscal' => -$total,      // Negativo para anular saldo
                        'cae_afip'     => $cae,
                        'parent_id'    => $facturaOriginal->id
                    ]);
                });

                return ['success' => true, 'message' => "Nota de Crédito generada."];
            } else {
                 $err = $res->FECAESolicitarResult->Errors->Err->Msg ?? 'Error desconocido';
                 return ['success' => false, 'error' => "Rechazo AFIP: $err"];
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