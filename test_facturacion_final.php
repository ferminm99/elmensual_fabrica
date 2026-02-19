<?php
// === CONFIGURACIÓN ===
$cuit = 30633784104; 
$pv   = 10; 
$base = "/var/www/elmensual/storage/app/afip/";
$wsdl = $base . "wsfev1.wsdl"; 
$cert = $base . "cert";
$key  = $base . "key";
$pathXml = $base . "xml/";

// Validaciones previas
if (!file_exists($pathXml)) mkdir($pathXml, 0777, true);
if (!file_exists($wsdl)) die("❌ ERROR CRÍTICO: Falta el archivo wsfev1.wsdl (mapa local).\n");

// Contexto de seguridad (SECLEVEL=1) para compatibilidad con AFIP
$opts = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true, 'ciphers' => 'DEFAULT@SECLEVEL=1']];
$context = stream_context_create($opts);

echo "--- TEST CICLO COMPLETO (FACTURA + ANULACIÓN) ---\n";

try {
    // ---------------------------------------------------------
    // 1. AUTENTICACIÓN (LOGIN)
    // ---------------------------------------------------------
    echo "1. Obteniendo Token... ";
    
    $tra = "<?xml version='1.0' encoding='UTF-8'?><loginTicketRequest version='1.0'>
    <header>
        <uniqueId>".time()."</uniqueId>
        <generationTime>".date('c',time()-600)."</generationTime>
        <expirationTime>".date('c',time()+600)."</expirationTime>
    </header>
    <service>wsfe</service>
</loginTicketRequest>";
file_put_contents($pathXml."TRA.xml", $tra);
$cmsPath = $pathXml."TRA.tmp";
openssl_pkcs7_sign($pathXml."TRA.xml", $cmsPath, "file://".$cert, ["file://".$key, ""], [], PKCS7_BINARY);

$inf = fopen($cmsPath, "r"); $cms = ""; $i = 0;
while (!feof($inf)) { $buffer = fgets($inf); if ($i++ >= 4 && trim($buffer)!="" && strpos($buffer,'Content-')===false &&
strpos($buffer,'--')===false) $cms .= trim($buffer); }
fclose($inf); $cms = preg_replace('/\s+/', '', $cms);

$wsaa = new SoapClient("https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL", ['soap_version' => SOAP_1_2,
'stream_context' => $context, 'exceptions' => true]);
$loginRes = $wsaa->loginCms(['in0' => $cms]);

$authXml = simplexml_load_string($loginRes->loginCmsReturn);
$token = (string)$authXml->credentials->token;
$sign = (string)$authXml->credentials->sign;

if (empty($token)) die("\n❌ ERROR: No se pudo obtener el Token.\n");

$auth = ['Token' => $token, 'Sign' => $sign, 'Cuit' => $cuit];
echo "OK\n";

// ---------------------------------------------------------
// 2. CONECTAR AL FACTURADOR (Usando WSDL Local)
// ---------------------------------------------------------
echo "2. Conectando al facturador... ";
$wsfe = new SoapClient("file://".$wsdl, ['soap_version' => SOAP_1_2, 'stream_context' => $context, 'exceptions' =>
true]);
echo "OK\n";

// ---------------------------------------------------------
// 3. FACTURAR
// ---------------------------------------------------------
$tipo_cbte = 6; // Factura B
$ult = $wsfe->FECompUltimoAutorizado(['Auth' => $auth, 'PtoVta' => $pv, 'CbteTipo' => $tipo_cbte]);
$next = $ult->FECompUltimoAutorizadoResult->CbteNro + 1;

echo "3. Creando Factura B #$next... ";

$factura = [
'Auth' => $auth,
'FeCAEReq' => [
'FeCabReq' => ['CantReg' => 1, 'PtoVta' => $pv, 'CbteTipo' => $tipo_cbte],
'FeDetReq' => [
'FECAEDetRequest' => [
'Concepto' => 1, 'DocTipo' => 99, 'DocNro' => 0,
'CbteDesde' => $next, 'CbteHasta' => $next, 'CbteFch' => date('Ymd'),
'ImpTotal' => 1.00, 'ImpTotConc' => 0, 'ImpNeto' => 0.83, 'ImpOpEx' => 0, 'ImpIVA' => 0.17, 'ImpTrib' => 0,
'MonId' => 'PES', 'MonCotiz' => 1,
'Iva' => ['AlicIva' => ['Id' => 5, 'BaseImp' => 0.83, 'Importe' => 0.17]]
]
]
]
];

$res = $wsfe->FECAESolicitar($factura);

if (isset($res->FECAESolicitarResult->FeCabResp->Resultado) && $res->FECAESolicitarResult->FeCabResp->Resultado == 'A')
{
// CORRECCIÓN: Leemos FECAEDetResponse, no Request
$cae = $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE;
echo "\n ✅ ¡ÉXITO! Factura creada. CAE: $cae\n";

// ---------------------------------------------------------
// 4. ANULAR (Nota de Crédito)
// ---------------------------------------------------------
echo "4. Anulando con Nota de Crédito... ";
$tipo_nc = 8; // NC B
$ultNC = $wsfe->FECompUltimoAutorizado(['Auth' => $auth, 'PtoVta' => $pv, 'CbteTipo' => $tipo_nc]);
$nextNC = $ultNC->FECompUltimoAutorizadoResult->CbteNro + 1;

$notaCredito = [
'Auth' => $auth,
'FeCAEReq' => [
'FeCabReq' => ['CantReg' => 1, 'PtoVta' => $pv, 'CbteTipo' => $tipo_nc],
'FeDetReq' => [
'FECAEDetRequest' => [
'Concepto' => 1, 'DocTipo' => 99, 'DocNro' => 0,
'CbteDesde' => $nextNC, 'CbteHasta' => $nextNC, 'CbteFch' => date('Ymd'),
'ImpTotal' => 1.00, 'ImpTotConc' => 0, 'ImpNeto' => 0.83, 'ImpOpEx' => 0, 'ImpIVA' => 0.17, 'ImpTrib' => 0,
'MonId' => 'PES', 'MonCotiz' => 1,
'Iva' => ['AlicIva' => ['Id' => 5, 'BaseImp' => 0.83, 'Importe' => 0.17]],
// CORRECCIÓN: Estructura plana para CbtesAsoc
'CbtesAsoc' => [
'CbteAsoc' => [
'Tipo' => $tipo_cbte,
'PtoVta' => $pv,
'Nro' => $next
]
]
]
]
]
];

$resNC = $wsfe->FECAESolicitar($notaCredito);

if ($resNC->FECAESolicitarResult->FeCabResp->Resultado == 'A') {
echo "✅ ¡ANULADO! Ciclo cerrado correctamente.\n";
} else {
echo "\n❌ ERROR AL ANULAR:\n";
print_r($resNC->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones);
}

} else {
echo "\n❌ ERROR AL FACTURAR:\n";
print_r($res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones);
}

} catch (Exception $e) {
echo "\n❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
}