<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Supplier;
use App\Models\Locality;

class ImportSuppliersCsv extends Command
{
    protected $signature = 'app:import-suppliers-csv';
    protected $description = 'Importa proveedores desde storage/app/proveedores.csv';

    public function handle()
    {
        // Asegurate de renombrar tu archivo a proveedores.csv o ajustar el nombre acá
        $path = storage_path('app/proveedores.csv');

        if (!file_exists($path)) {
            $this->error("No se encontró el archivo en {$path}");
            return;
        }

        $file = fopen($path, 'r');
        
        // Saltamos las primeras dos filas (títulos)
        fgetcsv($file);
        fgetcsv($file);

        $this->info("Iniciando importación de proveedores...");
        $bar = $this->output->createProgressBar();
        $bar->start();
        
        $count = 0;
        
        while (($row = fgetcsv($file)) !== false) {
            // Validamos columnas mínimas (hasta el nombre que es la 1)
            if (count($row) < 2) continue;

            $codigoProveedor = trim($row[0]);
            $nombre = trim($row[1]);
            $taxId = trim($row[4]); // Nro.Documento
            $inactivo = strtoupper(trim($row[17] ?? 'N')); // Columna Inactivo

            if (empty($codigoProveedor) || empty($nombre) || $inactivo === 'S') {
                continue;
            }

            // 1. TIPO DE IVA
            $taxConditionMap = [
                '01' => 'Responsable Inscripto',
                '07' => 'Consumidor Final',
                '08' => 'Monotributista',
                '04' => 'Exento'
            ];
            $taxConditionCode = trim($row[2]);
            $taxCondition = $taxConditionMap[$taxConditionCode] ?? 'Responsable Inscripto';

            // 2. BUSCAR LOCALIDAD (Columna 8 en el CSV de proveedores)
            $localityId = null;
            $localityCode = trim($row[8]);
            if (!empty($localityCode)) {
                $locality = Locality::where('code', $localityCode)->first();
                if ($locality) $localityId = $locality->id;
            }

            // 3. TELÉFONOS (Columnas 9, 10 y 11)
            $telefonos = array_filter([trim($row[9]), trim($row[10]), trim($row[11])]);
            $phone = implode(' / ', $telefonos);

            // 4. LÓGICA ANTI-DUPLICADOS
            $supplier = Supplier::where(function ($query) use ($codigoProveedor, $taxId, $nombre) {
                $query->where('code', $codigoProveedor)
                      ->orWhere('name', $nombre);
                
                if (!empty($taxId)) {
                    $query->orWhere('tax_id', $taxId);
                }
            })->first();

            $datosParaGuardar = [
                'code' => $codigoProveedor,
                'name' => $nombre,
                'tax_id' => $taxId,
                'tax_condition' => $taxCondition,
                'address' => trim($row[6]), // Domicilio
                'postal_code' => trim($row[7]) === '' ? null : trim($row[7]),
                'locality_id' => $localityId,
                'phone' => $phone,
                'email' => trim($row[15]) === '' ? null : trim($row[15]),
                'observations' => trim($row[16]) === '' ? null : trim($row[16]),
            ];

            if ($supplier) {
                $supplier->update($datosParaGuardar);
            } else {
                Supplier::create($datosParaGuardar);
            }

            $count++;
            $bar->advance();
        }

        $bar->finish();
        fclose($file);

        $this->newLine(2);
        $this->info("✅ ¡Se importaron o actualizaron {$count} proveedores exitosamente!");
    }
}