<?php
require __DIR__.'/vendor/autoload.php';

echo "--- INICIANDO TEST AFIP: LAMOTEX (MODO SYMLINK) --- \n";

try {
    $afip = new Afip([
        'CUIT'       => 30633784104,
        'production' => false,
        'cert'       => 'cert', // Ahora los encuentra en su carpeta local
        'key'        => 'key',
    ]);

    // Forzamos la carpeta de tokens para que no use la de vendor
    $afip->TA_folder = '/var/www/elmensual/storage/app/afip/xml/';

    echo "1. Verificando estado del servidor...\n";
    $status = $afip->ElectronicBilling->GetServerStatus();
    
    echo "¡CONEXIÓN EXITOSA!\n";
    echo "AppServer status: " . $status->AppServer . "\n";

} catch (Exception $e) {
    echo "--- ERROR --- \n";
    echo $e->getMessage() . "\n";
}