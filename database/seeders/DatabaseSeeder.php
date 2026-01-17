<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Article;
use App\Models\Size;
use App\Models\Color;
use App\Models\Sku;
use App\Models\PriceList;
use App\Models\Client;
use App\Models\CompanyAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Admin User
        User::factory()->create([
            'name' => 'Fermin Admin',
            'email' => 'admin@elmensual.com',
            'password' => Hash::make('password'),
        ]);

        // 2. Base Dictionaries
        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        foreach ($sizes as $s) Size::create(['name' => $s]);

        $colors = [
            ['name' => 'Black', 'hex' => '#000000'],
            ['name' => 'White', 'hex' => '#FFFFFF'],
            ['name' => 'Navy Blue', 'hex' => '#000080'],
            ['name' => 'Red', 'hex' => '#FF0000'],
        ];
        foreach ($colors as $c) Color::create(['name' => $c['name'], 'hex_code' => $c['hex']]);

        $categories = ['T-Shirts', 'Pants', 'Hoodies', 'Jackets'];
        foreach ($categories as $cat) Category::create(['name' => $cat]);

        // 3. Price Lists
        $retailList = PriceList::create(['name' => 'Retail (Final Consumer)', 'markup_percentage' => 50]);
        $wholesaleList = PriceList::create(['name' => 'Wholesale', 'markup_percentage' => 20]);

        // 4. Clients (Dual Accounting Scenarios)
        Client::create([
            'name' => 'Juan Perez (Fiscal Ok)',
            'tax_id' => '20123456789',
            'tax_condition' => 'Consumidor Final',
            'price_list_id' => $retailList->id,
            'account_balance_fiscal' => 150000.00, // Positive
            'account_balance_internal' => 0.00,
        ]);

        Client::create([
            'name' => 'Modas Sur (Debtor)',
            'tax_id' => '30777777772',
            'tax_condition' => 'Resp. Inscripto',
            'price_list_id' => $wholesaleList->id,
            'account_balance_fiscal' => -50000.00, // Owes money officially
            'account_balance_internal' => -200000.00, // Owes money internally
        ]);

        // 5. Articles & SKUs
        $tshirt = Article::create([
            'name' => 'Basic Cotton T-Shirt',
            'code' => 'REM-001',
            'base_cost' => 5000.00,
            'category_id' => 1, // T-Shirts
        ]);

        // Generate SKUs for this T-Shirt (Matrix of Size x Color)
        $sizeIds = Size::all();
        $colorIds = Color::all();

        foreach ($sizeIds as $size) {
            foreach ($colorIds as $color) {
                Sku::create([
                    'article_id' => $tshirt->id,
                    'size_id' => $size->id,
                    'color_id' => $color->id,
                    'stock_quantity' => rand(0, 100), // Random stock for testing alerts
                    'min_stock_alert' => 10,
                ]);
            }
        }

        // 6. Treasury
        CompanyAccount::create(['name' => 'Banco Galicia ARS', 'type' => 'Bank', 'currency' => 'ARS', 'current_balance' => 1000000]);
        CompanyAccount::create(['name' => 'Office Safe', 'type' => 'Cash', 'currency' => 'ARS', 'current_balance' => 500000]);
    }
}