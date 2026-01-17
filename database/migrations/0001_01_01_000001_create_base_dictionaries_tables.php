<?php
// database/migrations/0001_01_01_000001_create_base_dictionaries_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Index for lookups
            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'XL', '42'
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hex_code', 7)->nullable();
            $table->timestamps();
        });
        
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('markup_percentage', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('categories');
    }
};