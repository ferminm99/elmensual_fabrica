<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bank;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['code' => '007', 'name' => 'Banco Galicia'],
            ['code' => '011', 'name' => 'Banco Nación'],
            ['code' => '014', 'name' => 'Banco Provincia de Buenos Aires'],
            ['code' => '015', 'name' => 'ICBC'],
            ['code' => '016', 'name' => 'Citibank'],
            ['code' => '017', 'name' => 'BBVA (Francés)'],
            ['code' => '020', 'name' => 'Bancor (Banco de Córdoba)'],
            ['code' => '027', 'name' => 'Banco Supervielle'],
            ['code' => '029', 'name' => 'Banco de la Ciudad de Buenos Aires'],
            ['code' => '034', 'name' => 'Banco Patagonia'],
            ['code' => '044', 'name' => 'Banco Hipotecario'],
            ['code' => '045', 'name' => 'Banco de San Juan'],
            ['code' => '065', 'name' => 'Banco Municipal de Rosario'],
            ['code' => '072', 'name' => 'Banco Santander'],
            ['code' => '083', 'name' => 'Banco del Chubut'],
            ['code' => '086', 'name' => 'Banco de Santa Cruz'],
            ['code' => '093', 'name' => 'Banco de la Pampa'],
            ['code' => '097', 'name' => 'Banco de Corrientes'],
            ['code' => '150', 'name' => 'HSBC Bank'],
            ['code' => '191', 'name' => 'Banco Credicoop'],
            ['code' => '259', 'name' => 'Banco Macro BMA (Ex Itaú)'],
            ['code' => '268', 'name' => 'Banco Provincia del Neuquén'],
            ['code' => '285', 'name' => 'Banco Macro'],
            ['code' => '299', 'name' => 'Banco Comafi'],
            ['code' => '300', 'name' => 'Banco BICE'],
            ['code' => '301', 'name' => 'Banco Piano'],
            ['code' => '309', 'name' => 'Banco Rioja'],
            ['code' => '310', 'name' => 'Banco del Sol'],
            ['code' => '311', 'name' => 'Nuevo Banco del Chaco'],
            ['code' => '312', 'name' => 'Banco Voii'],
            ['code' => '315', 'name' => 'Banco de Formosa'],
            ['code' => '319', 'name' => 'Banco CMF'],
            ['code' => '321', 'name' => 'Banco de Santiago del Estero'],
            ['code' => '322', 'name' => 'Banco Industrial (BIND)'],
            ['code' => '330', 'name' => 'Nuevo Banco de Santa Fe'],
            ['code' => '340', 'name' => 'BACS Banco de Crédito y Securitización'],
            ['code' => '386', 'name' => 'Nuevo Banco de Entre Ríos'],
            ['code' => '389', 'name' => 'Banco Columbia'],
            ['code' => '426', 'name' => 'Banco BICA'],
            ['code' => '431', 'name' => 'Banco Coinag'],
            ['code' => '432', 'name' => 'Banco Julio'],
            ['code' => '448', 'name' => 'Banco Dino'],
            // --- BILLETERAS VIRTUALES (CVU) ---
            ['code' => '031', 'name' => 'Mercado Pago'],
            ['code' => '000', 'name' => 'Billetera Virtual (Genérica)'],
            ['code' => '991', 'name' => 'Ualá (Pency)'],
            ['code' => '001', 'name' => 'Brubank'],
            ['code' => '151', 'name' => 'Openbank'],
            ['code' => '010', 'name' => 'Reba (Rebanking)'],
            ['code' => '322', 'name' => 'Prex'],
            ['code' => '121', 'name' => 'Naranja X'],
            ['code' => '125', 'name' => 'Personal Pay'],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['code' => $bank['code']], 
                ['name' => $bank['name'], 'is_active' => true]
            );
        }
        
        echo "¡Todos los bancos de Argentina cargados!\n";
    }
}