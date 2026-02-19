<?php
// Configuración
$url = "https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL";
$destino = "/var/www/elmensual/storage/app/afip/wsfev1.wsdl";

echo "Intentando descargar WSDL desde AFIP...\n";

// Configuración para permitir seguridad "vieja" (Level 1)
$contexto = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
        'ciphers' => 'DEFAULT@SECLEVEL=1', // <--- LA MAGIA: Baja el nivel de seguridad
    ]
]);

$contenido = file_get_contents($url, false, $contexto);

if ($contenido) {
    file_put_contents($destino, $contenido);
    echo "✅ ¡ÉXITO! Archivo guardado en: $destino\n";
    echo "Tamaño: " . strlen($contenido) . " bytes.\n";
} else {
    echo "❌ ERROR: No se pudo descargar el contenido.\n";
    print_r(error_get_last());
}