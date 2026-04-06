<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\Locality;
use App\Models\Salesman;
use App\Models\PriceList;

class ImportClientsCsv extends Command
{
    protected $signature = 'app:import-clients-csv';
    protected $description = 'Importa clientes desde storage/app/clientes.csv con todos los datos extra';

    public function handle()
    {
        $path = storage_path('app/clientes.csv');

        if (!file_exists($path)) {
            $this->error("No se encontró el archivo en {$path}");
            return;
        }

        $file = fopen($path, 'r');
        
        // Saltamos las primeras dos filas (títulos)
        fgetcsv($file);
        fgetcsv($file);

        $this->info("Iniciando importación avanzada de clientes...");
        $bar = $this->output->createProgressBar();
        $bar->start();
        
        $count = 0;
        
        while (($row = fgetcsv($file)) !== false) {
            // Asegurarnos de que tenga al menos las columnas necesarias (hasta el nombre)
            if (count($row) < 25) continue;

            $codigoCliente = trim($row[23]);
            $nombre = trim($row[24]);
            $estado = trim($row[16]); // A (Activo), I (Inactivo), etc.
            $postalCode = trim($row[4]) === '' ? null : trim($row[4]); 
            $observaciones = trim($row[19] ?? '') === '' ? null : trim($row[19]);

            // Ignoramos si no tiene código, nombre, o si está Inactivo/Dado de baja (opcional, podés sacarlo si querés traer todos)
            if (empty($codigoCliente) || empty($nombre) || $estado !== 'A') {
                continue;
            }

            // 1. TIPO DE IVA
            $taxConditionMap = [
                '01' => 'Responsable Inscripto', // Ajustá estos textos según cómo los guardes en tu app
                '07' => 'Consumidor Final',
                '08' => 'Monotributista',
                '04' => 'Exento'
            ];
            $taxConditionCode = trim($row[0]);
            $taxCondition = $taxConditionMap[$taxConditionCode] ?? null;

            // 2. BUSCAR LOCALIDAD
            $localityId = null;
            $localityCode = trim($row[5]);
            if (!empty($localityCode)) {
                $locality = Locality::where('code', $localityCode)->first();
                if ($locality) $localityId = $locality->id;
            }

            // 3. BUSCAR VENDEDOR (Columna 11)
            $salesmanId = null;
            $salesmanCode = trim($row[11]);
            if (!empty($salesmanCode)) {
                $salesman = Salesman::where('code', $salesmanCode)->first();
                if ($salesman) $salesmanId = $salesman->id;
            }

            // 4. BUSCAR LISTA DE PRECIOS (Columna 14)
            $priceListId = null;
            $priceListCode = trim($row[14]);
            if (!empty($priceListCode)) {
                // Busca si la lista de precios existe por nombre o código
                $priceList = PriceList::where('name', 'like', "%{$priceListCode}%")->first();
                if ($priceList) $priceListId = $priceList->id;
            }

            // 5. COMBINAR TELÉFONOS (Columnas 7, 8 y 9)
            $telefonos = array_filter([trim($row[7]), trim($row[8]), trim($row[9])]);
            $phone = implode(' / ', $telefonos);

            // 6. CAZADOR DE DESCUENTOS EN OBSERVACIONES (Columnas 19 a 22)
            $descuento = 0;
            for ($i = 19; $i <= 22; $i++) {
                if (isset($row[$i]) && str_contains($row[$i], '%')) {
                    // Usamos Regex para capturar EXACTAMENTE el número antes del %
                    // Ej: En "2 14,5% VARELA 3096" capturará "14,5"
                    if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $row[$i], $matches)) {
                        $numeroLimpio = str_replace(',', '.', $matches[1]);
                        $descuento = (float) $numeroLimpio;
                        break; 
                    }
                }
            }

            // 7. BUSCAR Y ACTUALIZAR O CREAR (LÓGICA ANTI-DUPLICADOS)
            $taxId = trim($row[2]);

            // Buscamos al cliente por Código, o por CUIT, o por Nombre
            $client = Client::where(function ($query) use ($codigoCliente, $taxId, $nombre) {
                $query->where('code', $codigoCliente)
                      ->orWhere('name', $nombre);
                
                if (!empty($taxId)) {
                    $query->orWhere('tax_id', $taxId);
                }
            })->first();

            $datosParaGuardar = [
                'code' => $codigoCliente, // Le inyectamos el código al cliente viejo
                'name' => $nombre,
                'tax_id' => $taxId,
                'tax_condition' => $taxCondition,
                'address' => trim($row[3]),
                'postal_code' => $postalCode,
                'observations' => $observaciones,
                'locality_id' => $localityId,
                'phone' => $phone,
                'email' => trim($row[18]),
                'salesman_id' => $salesmanId,
                'price_list_id' => $priceListId,
                'default_discount' => $descuento,
            ];

            if ($client) {
                // Si lo encontró (es uno viejo con pedidos), lo actualiza
                $client->update($datosParaGuardar);
            } else {
                // Si realmente no existe en la base, lo crea
                Client::create($datosParaGuardar);
            }

            $count++;
            $bar->advance();
        }

        $bar->finish();
        fclose($file);

        $this->newLine(2);
        $this->info("✅ ¡Se importaron o actualizaron {$count} clientes activos exitosamente con todos sus datos!");
    }
}