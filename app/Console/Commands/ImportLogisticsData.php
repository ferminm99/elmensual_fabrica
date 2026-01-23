<?php

namespace App\Console\Commands;

use App\Models\{Client, Locality, Zone, Salesman};
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;

class ImportLogisticsData extends Command
{
    protected $signature = 'import:logistics';

    public function handle()
    {
        $path = storage_path('app/clientes.pdf');
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();
        $lines = explode("\n", $text);

        $temp = ['name' => null, 'loc' => null, 'zone' => null, 'salesman' => null];

        foreach ($lines as $i => $line) {
            $line = trim($line);

            if (str_starts_with($line, 'Nombre:')) {
                $temp['name'] = trim(str_replace('Nombre:', '', $line)) ?: trim($lines[$i+1] ?? '');
            }

            if (str_contains($line, 'Localidad:')) {
                $val = trim(str_replace('Localidad:', '', $line)) ?: trim($lines[$i+1] ?? '');
                if (preg_match('/(\d{4})\s*-\s*(.+)/', $val, $m)) {
                    $temp['loc'] = ['code' => $m[1], 'name' => trim($m[2])];
                }
            }

            if (str_contains($line, 'Zona:')) {
                $val = trim(str_replace('Zona:', '', $line)) ?: trim($lines[$i+1] ?? '');
                if (preg_match('/([\d\s-]+)\s*-\s*(.+)/', $val, $m)) {
                    $temp['zone'] = ['code' => trim($m[1]), 'name' => trim($m[2])];
                }
            }

            // CAPTURAR VIAJANTE
            if (str_contains($line, 'Vendedor:')) {
                $val = trim(str_replace('Vendedor:', '', $line)) ?: trim($lines[$i+1] ?? '');
                if (preg_match('/(\d+)\s*-\s*(.+)/', $val, $m)) {
                    $temp['salesman'] = Salesman::firstOrCreate(
                        ['code' => $m[1]], 
                        ['name' => trim($m[2])]
                    );
                }
            }

            // LÍNEA MAESTRA (Disparador)
            if (preg_match('/^\d{5}\s+\d{2}\s+\d{2}\s+(\d{11})\s+(.+)$/', $line, $m)) {
                $cuit = $m[1];
                $address = trim(preg_replace('/\s+/', ' ', preg_replace('/\d{4}.*$/', '', $m[2])));

                $z = $temp['zone'] ? Zone::updateOrCreate(['code' => $temp['zone']['code']], ['name' => $temp['zone']['name']]) : null;
                $l = $temp['loc'] ? Locality::updateOrCreate(['code' => $temp['loc']['code']], ['name' => $temp['loc']['name'], 'zone_id' => $z?->id]) : null;

                // Si el viajante no tiene esta zona vinculada, la vinculamos
                if ($temp['salesman'] && $z) {
                    $temp['salesman']->zones()->syncWithoutDetaching([$z->id]);
                }

                Client::updateOrCreate(['tax_id' => $cuit], [
                    'name' => trim(preg_replace('/\s+/', ' ', $temp['name'])),
                    'address' => $address,
                    'locality_id' => $l?->id,
                    'salesman_id' => $temp['salesman']?->id,
                ]);

                $this->info("Importado: " . $temp['name']);
                $temp = ['name' => null, 'loc' => null, 'zone' => null, 'salesman' => null];
            }
        }
    }
}