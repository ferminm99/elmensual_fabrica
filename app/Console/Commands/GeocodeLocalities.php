<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Locality;
use Illuminate\Support\Facades\Http;

class GeocodeLocalities extends Command
{
    // Agregamos {--force} para poder obligarlo a re-escanear todo
    protected $signature = 'app:geocode-localities {--force}';
    protected $description = 'Obtiene coordenadas y bordes usando el Código Postal de los clientes';

    public function handle()
    {
        // Si usamos --force, traemos todas para pisar el error de La Plata. 
        // Si no, solo las que les falta el GeoJSON.
        if ($this->option('force')) {
            $localities = Locality::all();
        } else {
            $localities = Locality::whereNull('geojson')->get();
        }
        
        if ($localities->isEmpty()) {
            $this->info('Todas las localidades ya tienen su mapa asignado.');
            return;
        }

        $this->info("Iniciando escaneo inteligente para {$localities->count()} localidades...");
        $this->newLine();

        $exitosos = [];
        $fallidos = [];

        $bar = $this->output->createProgressBar(count($localities));
        $bar->start();

        foreach ($localities as $locality) {
            // Buscamos un CP válido entre los clientes de esta localidad
            $cp = $locality->clients()
                ->whereNotNull('postal_code')
                ->whereRaw("TRIM(postal_code) != ''")
                ->value('postal_code');

            $cp = trim((string) $cp);

            // Armamos un array de intentos. Va a probar uno por uno de mayor a menor precisión
            $queries = [];
            
            if (!empty($cp)) {
                // 1. Super preciso: Código Postal + Nombre de localidad
                $queries[] = "{$cp} {$locality->name}, Argentina";
                // 2. Plan B: Solo el Código Postal (a veces el nombre de tu sistema difiere un poco del oficial)
                $queries[] = "{$cp}, Argentina"; 
            }
            
            // 3. Plan C (o Plan A si no hay CP): Nombre + Buenos Aires
            $queries[] = "{$locality->name}, Provincia de Buenos Aires, Argentina";
            // 4. Plan D: Solo el nombre y Argentina (por si es de otra provincia)
            $queries[] = "{$locality->name}, Argentina";

            $encontrado = false;

            foreach ($queries as $query) {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => 'ERP-Fabrica/2.0', 
                    ])->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $query,
                        'format' => 'json',
                        'limit' => 1,
                        'polygon_geojson' => 1 // Trae el borde exacto
                    ]);

                    $data = $response->json();

                    if ($response->successful() && count($data) > 0) {
                        $resultado = $data[0];
                        
                        // Si la latitud que nos devuelve es exactamente la de La Plata (por default), seguimos intentando
                        if (round($resultado['lat'], 2) == '-34.92' && !str_contains(strtolower($query), 'la plata')) {
                            sleep(1);
                            continue;
                        }

                        $locality->update([
                            'lat' => $resultado['lat'],
                            'lng' => $resultado['lon'], 
                            'geojson' => $resultado['geojson'] ?? null,
                        ]);

                        $exitosos[] = [
                            $cp ?: 'Sin CP',
                            $locality->name,
                            $resultado['display_name'], // Esto te mostrará la ciudad real que encontró
                            isset($resultado['geojson']) ? '✅' : '❌'
                        ];
                        $encontrado = true;
                        break; // ¡Lo encontró! Rompemos este bucle interno y pasamos a la siguiente localidad
                    }
                } catch (\Exception $e) {
                    // Si hay error de conexión, no hace nada y pasa a la siguiente opción
                }
                
                // Pausa OBLIGATORIA de 1 segundo para no ser bloqueados por OpenStreetMap
                sleep(1); 
            }

            if (!$encontrado) {
                $fallidos[] = [$locality->name, $cp ?: 'Sin CP'];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (!empty($exitosos)) {
            $this->info('✅ MAPAS ASIGNADOS CORRECTAMENTE:');
            $this->table(['CP Usado', 'Localidad BD', 'Ciudad Real (Según Mapa)', 'Borde GeoJSON'], $exitosos);
        }

        if (!empty($fallidos)) {
            $this->newLine();
            $this->error('❌ NO SE PUDO GEOLOCALIZAR (Revisar nombres a mano):');
            $this->table(['Localidad', 'CP Intentado'], $fallidos);
        }
    }
}