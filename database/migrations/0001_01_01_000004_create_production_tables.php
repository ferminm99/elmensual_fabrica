<?php
// database/migrations/0001_01_01_000004_create_production_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // SKU Base / Model Code
            $table->decimal('base_cost', 15, 2)->default(0);
            $table->foreignId('category_id')->constrained();
            $table->timestamps();
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_id')->constrained();
            $table->foreignId('color_id')->constrained();
            
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_alert')->default(0);
            
            $table->timestamps();

            // Ensure unique combination of Article+Size+Color
            $table->unique(['article_id', 'size_id', 'color_id']);
        });

        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('unit', ['Meters', 'Kilos', 'Units']);
            $table->decimal('stock_quantity', 12, 3)->default(0); // 3 decimals for kilos/meters
            $table->decimal('avg_cost', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained();
            
            $table->decimal('quantity_required', 10, 4);
            $table->decimal('waste_percent', 5, 2)->default(0);
            
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained();
            $table->integer('quantity');
            $table->enum('status', ['Planned', 'Cutting', 'Workshop', 'Finished'])->default('Planned')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('raw_materials');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('articles');
    }
};