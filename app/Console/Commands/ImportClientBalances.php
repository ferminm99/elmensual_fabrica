<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ImportClientBalances extends Command
{
    protected $signature = 'app:import-client-balances';
    protected $description = 'Importa saldos de clientes (Blanco y Negro) desde CSVs';

    public function handle()
    {
        $blancoPath = storage_path('app/saldos_blanco.csv');
        $negroPath = storage_path('app/saldos_negro.csv');

        if (!file_exists($blancoPath) || !file_exists($negroPath)) {
            $this->error("❌ Faltan archivos. Asegurate de tener 'saldos_blanco.csv' y 'saldos_negro.csv' en storage/app/");
            return;
        }

        // Ponemos todas las deudas a 0 para que el sistema quede exactamente igual a los Excel.
        // Si un cliente no aparece en los Excel, significa que no debe nada (saldo 0).
        $this->info("🔄 Reiniciando deudas actuales a $0 para limpiar historial...");
        Client::query()->update(['fiscal_debt' => 0, 'internal_debt' => 0]);

        $balances = [];

        // Procesar Blanco
        $this->info("📄 Procesando archivo BLANCO (saldos_blanco.csv)...");
        $this->processFile($blancoPath, 'fiscal_debt', $balances);

        // Procesar Negro
        $this->info("📄 Procesando archivo NEGRO (saldos_negro.csv)...");
        $this->processFile($negroPath, 'internal_debt', $balances);

        $this->info("💾 Guardando los saldos consolidados en la base de datos...");
        
        $notFound = [];
        $bar = $this->output->createProgressBar(count($balances));
        $bar->start();

        DB::beginTransaction();
        try {
            foreach ($balances as $code => $debts) {
                // Buscamos al cliente por el código
                $client = Client::where('code', $code)->first();
                
                if ($client) {
                    $client->update([
                        'fiscal_debt' => $debts['fiscal_debt'] ?? 0,
                        'internal_debt' => $debts['internal_debt'] ?? 0,
                    ]);
                } else {
                    $notFound[] = $code; // Guardamos los que no encontró para avisarte
                }
                $bar->advance();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ Error al guardar en base de datos: " . $e->getMessage());
            return;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ ¡Saldos importados y calculados exitosamente!");

        // Te avisa si hay algún cliente en los Excel que no tenés en tu base de datos
        if (count($notFound) > 0) {
            $this->warn("⚠️ No se encontraron en la BD los siguientes códigos de clientes: " . implode(', ', $notFound));
        }
    }

    /**
     * Lee el archivo y acumula (Débitos - Créditos) por código de cliente
     */
    private function processFile($path, $type, &$balances)
    {
        $file = fopen($path, 'r');

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 8) continue;

            $clientString = trim($row[0]);
            
            if (empty($clientString) || str_starts_with($clientString, 'Vencimientos') || str_starts_with($clientString, 'Analítica')) {
                continue;
            }

            if (preg_match('/\((\d+)\)/', $clientString, $matches)) {
                $code = $matches[1];
                
                // Usamos la nueva función inteligente para limpiar el Excel
                $debito = $this->limpiarNumero($row[6] ?? '0');
                $credito = $this->limpiarNumero($row[7] ?? '0');

                if (!isset($balances[$code])) {
                    $balances[$code] = ['fiscal_debt' => 0, 'internal_debt' => 0];
                }

                $balances[$code][$type] += ($debito - $credito);
            }
        }

        fclose($file);
    }

    /**
     * Limpia los formatos de Excel (puntos y comas) para que PHP no corte los números
     */
    private function limpiarNumero($valor)
    {
        $valor = trim((string) $valor);
        if (empty($valor)) return 0;

        // Si tiene punto y coma a la vez (ej: 1.234,56 o 1,234.56)
        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $lastComma = strrpos($valor, ',');
            $lastDot = strrpos($valor, '.');
            
            if ($lastComma > $lastDot) {
                // Formato AR (1.234,56): borramos el punto de miles y cambiamos coma por punto
                $valor = str_replace('.', '', $valor); 
                $valor = str_replace(',', '.', $valor); 
            } else {
                // Formato US (1,234.56): borramos la coma de miles directo
                $valor = str_replace(',', '', $valor); 
            }
        } elseif (str_contains($valor, ',')) {
            // Si solo tiene coma, asumimos que es el decimal argentino (ej: 1500,50)
            $valor = str_replace(',', '.', $valor);
        }

        // Limpiamos cualquier otra basura (signos $, letras, etc) dejando solo números, puntos y el signo menos
        $valor = preg_replace('/[^0-9\.-]/', '', $valor);
        
        return (float) $valor;
    }
}