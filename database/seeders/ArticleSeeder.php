<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        // Desactivamos chequeo de claves foráneas por un momento para limpiar rápido si hiciera falta
        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Article::truncate(); 
        // Category::truncate();
        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $data = [
            'BOMBACHA RECTA (GRAFA 70 PESADA)' => [
                ['code' => '100', 'name' => 'BOMBACHA RECTA PESADA TALLE 36 AL 54', 'price' => 15785.00],
                ['code' => '101', 'name' => 'BOMBACHA RECTA PESADA TALLE ESPECIAL 56 AL 60', 'price' => 17710.00],
                ['code' => '107', 'name' => 'BOMBACHA RECTA PESADA LARGO ESPECIAL', 'price' => 17760.00],
                ['code' => '111', 'name' => 'BOMBACHA RECTA PESADA CON CIERRE', 'price' => 15785.00],
                ['code' => '113', 'name' => 'BOMBACHA RECTA PESADA LARGO ESP/ TALLE ESPECIAL', 'price' => 19770.00],
                ['code' => '114-P', 'name' => 'BOMBACHA RECTA PESADA TALLE SUPER ESPECIAL 62 AL 66', 'price' => 20824.00],
                ['code' => '116', 'name' => 'BOMBACHA RECTA PESADA CORTA ESPECIAL', 'price' => 15750.00],
                ['code' => '157', 'name' => 'BOMBACHA RECTA PESADA CORTA ESP. TALLE ESPECIAL', 'price' => 17430.00],
                ['code' => '445', 'name' => 'BOMBACHA RECTA PESADA EXTRA SUPER ESPECIAL 68 Y 70', 'price' => 22715.00],
                ['code' => '1450', 'name' => 'BOMBACHA DAMA TIRO BAJO TALLE 32 AL 50 PESADA', 'price' => 15785.00],
                ['code' => '118-P', 'name' => 'BOMBACHA NIÑO RECTA C/CIERRE TALLE 0 AL 8 PESADA', 'price' => 11780.00],
                ['code' => '119-P', 'name' => 'BOMBACHA NIÑO RECTA C/CIERRE TALLE 10 AL 16 PESADA', 'price' => 11980.00],
                ['code' => '171', 'name' => 'BOMBACHA GABARDINA 2 BOLSILLOS TALLE 36 AL 54', 'price' => 18880.00],
                ['code' => '175', 'name' => 'BOMBACHA RECTA PESADA DAMA TIRO BAJO L/ESP.', 'price' => 17710.00],
            ],
            'BOMBACHA RECTA (GRAFA 70 LIVIANA)' => [
                ['code' => '109', 'name' => 'BOMBACHA RECTA LIVIANA TALLE 36 AL 54 C/IND.', 'price' => 15470.00],
                ['code' => '110', 'name' => 'BOMBACHA RECTA LIVIANA TALLE ESPECIAL 56 AL 60', 'price' => 17300.00],
                ['code' => '114-L', 'name' => 'BOMBACHA RECTA LIVIANA TALLE SUPER ESPECIAL 62 AL 66', 'price' => 20825.00],
                ['code' => '121', 'name' => 'BOMBACHA RECTA LIVIANA LARGO ESPECIAL TALLE 36 AL 54', 'price' => 17570.00],
                ['code' => '135', 'name' => 'BOMBACHA RECTA LIVIANA CON CIERRE TALLE 36 AL 54', 'price' => 15470.00],
                ['code' => '138', 'name' => 'BOMBACHA RECTA LIVIANA CORTA ESPECIAL', 'price' => 15400.00],
                ['code' => '145', 'name' => 'BOMBACHA DAMA TIRO BAJO TALLE 32 AL 50 LIVIANA', 'price' => 15470.00],
                ['code' => '118-L', 'name' => 'BOMBACHA NIÑO RECTA C/CIERRE TALLE 0 AL 8 LIVIANA', 'price' => 11780.00],
                ['code' => '119-L', 'name' => 'BOMBACHA NIÑO RECTA C/CIERRE TALLE 10 AL 16 LIVIANA', 'price' => 11980.00],
            ],
            'BOMBACHAS ALFORZADAS Y OTRAS' => [
                ['code' => '102', 'name' => 'BOMBACHA ALFORZADA PESADA TALLE 36 AL 56', 'price' => 17155.00],
                ['code' => '120', 'name' => 'BOMBACHA ALFORZADA LIVIANA TALLE 36 AL 56', 'price' => 16960.00],
                ['code' => '103', 'name' => 'BOMBACHA BATARAZA NIÑO TALLE 00 AL 08', 'price' => 14425.00],
                ['code' => '104', 'name' => 'BOMBACHA BATARAZA NIÑO TALLE 10 AL 16', 'price' => 14680.00],
                ['code' => '133', 'name' => 'BOMBACHA BATARAZA TALLE 36 AL 54', 'price' => 19275.00],
                ['code' => '152', 'name' => 'BOMBACHA POLYESTER-VISCOZA (ALPACUNA) 2 BOLSILLOS', 'price' => 26290.00],
            ],
            'ALPARGATAS' => [
                ['code' => '212', 'name' => 'ALPARGATA NIÑO REFORZADA EN PUNTERA DEL 20 AL 33', 'price' => 3698.00],
                ['code' => '213', 'name' => 'ALPARGATA REFORZADA EN PUNTERA Y TALON DEL 34 AL 45', 'price' => 4488.00],
                ['code' => '214', 'name' => 'ALPARGATA ESPECIAL DEL 46 AL 50 NEGRA Y BLANCA', 'price' => 4940.00],
                ['code' => '215', 'name' => 'ALPARGATA SIN REFUERZO - MOCASIN - 34 AL 45', 'price' => 4217.00],
                ['code' => '216', 'name' => 'ALPARGATA CON CORDONES DEL 34 AL 45', 'price' => 4542.00],
                ['code' => '217', 'name' => 'ALPARGATA GUARDA PAMPA DEL 34 AL 44', 'price' => 4706.00],
                ['code' => '218', 'name' => 'ALPARGATA SIMIL CARPINCHO DEL 34 AL 45', 'price' => 8692.00],
                ['code' => '219', 'name' => 'ALPARGATA SIMIL CARPINCHO DEL 20 AL 33', 'price' => 7653.00],
                ['code' => '220', 'name' => 'ALPARGATAS DE SIMIL CARPINCHO 46 AL 50', 'price' => 8840.00],
                ['code' => '221', 'name' => 'ALPARGATA COMBINADA 34 AL 45', 'price' => 4706.00],
                ['code' => '223', 'name' => 'ALPARGATA PVC (TIPO YUTE)', 'price' => 7445.00],
                ['code' => '225', 'name' => 'ALPARGATA CON CORDON NIÑO DEL 25 AL 33', 'price' => 3853.00],
            ],
            'BOMBACHA VESTIR POPLIN LIVIANO' => [
                ['code' => '117', 'name' => 'BOMBACHA POPLIN (GRAFIL) TALLE ESPECIAL 56 AL 60', 'price' => 19010.00],
                ['code' => '125', 'name' => 'BOMBACHA POPLIN (GRAFIL) NIÑO TALLE 10 AL 16', 'price' => 14440.00],
                ['code' => '125/08', 'name' => 'BOMBACHA POPLIN (GRAFIL) NIÑO TALLE 00 AL 08', 'price' => 13470.00],
                ['code' => '148', 'name' => 'BOMBACHA DAMA POPLIN (GRAFIL) TIRO BAJO TALLE 34 AL 50', 'price' => 17785.00],
                ['code' => '155', 'name' => 'BOMBACHA POPLIN (GRAFIL) TALLE 38 AL 54', 'price' => 17785.00],
                ['code' => '158', 'name' => 'BOMBACHA POPLIN (GRAFIL) TALLE 38 AL 54 LARGO ESPECIAL', 'price' => 19685.00],
                ['code' => '162', 'name' => 'BOMBACHA HOMBRE FANTASIA 2 BOLSILLO TALLE 38 AL 54', 'price' => 20080.00],
                ['code' => '168', 'name' => 'BOMBACHA NIÑO FANTASIA TALLE 0 AL 8', 'price' => 15320.00],
                ['code' => '169', 'name' => 'BOMBACHA NIÑO FANTASIA TALLE 10 AL 16', 'price' => 15870.00],
                ['code' => '165', 'name' => 'BOMBACHA DAMA TIRO BAJO FANTASIA TALLE 34 AL 46', 'price' => 19010.00],
                ['code' => '178', 'name' => 'BOMBACHA GRAFIL CORTA ESPECIAL TALLE 36 AL 54', 'price' => 17445.00],
                ['code' => '179', 'name' => 'BOMBACHA FANTASIA JASPEADA TALLE 36 AL 54', 'price' => 17445.00],
                ['code' => '183', 'name' => 'BOMBACHA ESPANDER TALLE 36 AL 54', 'price' => 17785.00],
            ],
            'PANTALONES Y CORDEROY' => [
                ['code' => '112', 'name' => 'BOMBACHA CORDEROY TALLE 36 AL 54', 'price' => 33920.00],
                ['code' => '126', 'name' => 'BOMBACHA CORDEROY LARGO ESPECIAL TALLE 36 AL 54', 'price' => 38220.00],
                ['code' => '127', 'name' => 'BOMBACHA CORDEROY TALLE ESPECIAL 56 AL 60', 'price' => 36570.00],
                ['code' => '128', 'name' => 'BOMBACHA CORDEROY NIÑO TALLE 00 AL 08', 'price' => 26740.00],
                ['code' => '129', 'name' => 'BOMBACHA CORDEROY NIÑO TALLE 10 AL 16', 'price' => 27040.00],
                ['code' => '153', 'name' => 'PANTALON DE CORDEROY TALLE 40 AL 54', 'price' => 33920.00],
                ['code' => '154', 'name' => 'BOMBACHA DAMA TIRO BAJO CODEROY TALLE 34 AL 48', 'price' => 33920.00],
                ['code' => '180', 'name' => 'BOMBACHA CORDEROY CORTA ESPECIAL TALLE 36 AL 54', 'price' => 33185.00],
                ['code' => '105', 'name' => 'PANTALON DE TRABAJO TALLE 38 AL 54', 'price' => 15785.00],
                ['code' => '141', 'name' => 'PANTALON CARGO (BOLSILLO LATERAL) TALLE 38 AL 54', 'price' => 23955.00],
                ['code' => '201', 'name' => 'PANTALON DE TRABAJO TALLE ESPECIAL 62 AL 66', 'price' => 19150.00],
                ['code' => '202', 'name' => 'PANTALON DE TRABAJO TALLE ESPECIAL 56 AL 60', 'price' => 17370.00],
                ['code' => '142', 'name' => 'PANTALON SPORT/POPLIN (GRAFIL) TALLE 38 AL 54', 'price' => 17780.00],
                ['code' => '144', 'name' => 'PANTALON SPORT/POPLIN (GRAFIL) TALLE ESPECIAL 56 AL 60', 'price' => 19620.00],
                ['code' => '203', 'name' => 'PANTALON DE TRABAJO TALLE SUP. ESPECIAL 68/70', 'price' => 21985.00],
                ['code' => '170', 'name' => 'PANTALON SPORT GABARDINA PINZADO', 'price' => 18880.00],
            ],
            'CAMISAS Y BERMUDAS' => [
                ['code' => '106', 'name' => 'CAMISA DE TRABAJO TALLE 36 AL 46', 'price' => 15765.00],
                ['code' => '108', 'name' => 'CAMISA DE TRABAJO TALLE ESPECIAL 48 AL 54', 'price' => 17745.00],
                ['code' => '147', 'name' => 'CAMISA SCOUT NIÑO TALLE 30 AL 36', 'price' => 15120.00],
                ['code' => '156', 'name' => 'CAMISA GRAFIL TALLE 36 AL 46 CON CHARRETERA', 'price' => 17910.00],
                ['code' => '177', 'name' => 'CAMISA GABARDINA MANGA CORTA CON CHARRETERA T 38/46', 'price' => 16470.00],
                ['code' => '139', 'name' => 'BERMUDA POPLIN TALLE 36 AL 54', 'price' => 12260.00],
                ['code' => '140', 'name' => 'BERMUDA POPLIN CARGO (BOLSILLO LATERAL) TALLE 36 AL 54', 'price' => 17085.00],
                ['code' => '143', 'name' => 'BERMUDA POPLIN TALLE ESPECIAL 56 AL 60', 'price' => 14165.00],
            ],
            'VARIOS (JEANS Y BOINAS)' => [
                ['code' => '600', 'name' => 'BOINAS DE HILO', 'price' => 7560.00],
                ['code' => '149', 'name' => 'JEAN RECTO RIGIDO TALLE 38 AL 54', 'price' => 16128.00],
                ['code' => '150', 'name' => 'JEAN CON EXPANDER RECTO TALLE 38 AL 50', 'price' => 16128.00],
                ['code' => '411', 'name' => 'BOMBACHA RECTA PESADA TALLE 36 AL 54 (OFERTA)', 'price' => 13775.00],
                ['code' => '409', 'name' => 'BOMBACHA RECTA LIVIANA TALLE 36 AL 54 (OFERTA)', 'price' => 13630.00],
            ]
        ];

        foreach ($data as $categoryName => $articles) {
            // Buscamos o creamos la categoría
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($articles as $articleData) {
                // Buscamos o creamos el artículo (basado en el código)
                // Usamos updateOrCreate para que si ya existe, le actualice el precio
                Article::updateOrCreate(
                    ['code' => $articleData['code']], // Búsqueda por SKU
                    [
                        'name' => $articleData['name'],
                        'base_cost' => $articleData['price'],
                        'category_id' => $category->id,
                        // 'stock_quantity' => 100 // Si quisieras darles stock inicial
                    ]
                );
            }
        }
    }
}