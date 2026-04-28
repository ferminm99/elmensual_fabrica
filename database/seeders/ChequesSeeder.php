<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Check;

class ChequesSeeder extends Seeder
{
    public function run(): void
    {
        $cliente1 = Client::first();
        $cliente2 = Client::skip(1)->first() ?? $cliente1;

        if (!$cliente1) {
            echo "¡Ojo! No tenés clientes creados. Creá al menos uno en el sistema primero.\n";
            return;
        }

        $cheques = [
            ['number' => 'CHQ-001', 'amount' => 50000, 'is_echeq' => false, 'origin' => 'Fiscal', 'client_id' => $cliente1->id],
            ['number' => 'CHQ-002', 'amount' => 125000, 'is_echeq' => true, 'origin' => 'Fiscal', 'client_id' => $cliente1->id],
            ['number' => 'CHQ-003', 'amount' => 80000, 'is_echeq' => false, 'origin' => 'Internal', 'client_id' => $cliente1->id],
            ['number' => 'CHQ-004', 'amount' => 30000, 'is_echeq' => false, 'origin' => 'Internal', 'client_id' => $cliente2->id],
            ['number' => 'CHQ-005', 'amount' => 250000, 'is_echeq' => true, 'origin' => 'Fiscal', 'client_id' => $cliente2->id],
            ['number' => 'CHQ-006', 'amount' => 45000, 'is_echeq' => false, 'origin' => 'Fiscal', 'client_id' => $cliente2->id],
            ['number' => 'CHQ-007', 'amount' => 90000, 'is_echeq' => true, 'origin' => 'Internal', 'client_id' => $cliente1->id],
            ['number' => 'CHQ-008', 'amount' => 150000, 'is_echeq' => true, 'origin' => 'Fiscal', 'client_id' => $cliente1->id],
            ['number' => 'CHQ-009', 'amount' => 60000, 'is_echeq' => false, 'origin' => 'Internal', 'client_id' => $cliente2->id],
            ['number' => 'CHQ-010', 'amount' => 110000, 'is_echeq' => true, 'origin' => 'Fiscal', 'client_id' => $cliente2->id],
        ];

        foreach ($cheques as $ch) {
            Check::create([
                'number' => $ch['number'],
                'amount' => $ch['amount'],
                'is_echeq' => $ch['is_echeq'],
                'origin' => $ch['origin'],
                'client_id' => $ch['client_id'],
                'status' => 'InPortfolio', 
                'type' => 'ThirdParty', // <--- AGREGADO: Es de Terceros
                'bank_name' => 'Banco Galicia', // <--- CORREGIDO: bank_name
                'payment_date' => now()->addDays(rand(10, 30)), // (Sacamos emission_date)
            ]);
        }
        
        echo "¡10 Cheques creados en cartera exitosamente!\n";
    }
}