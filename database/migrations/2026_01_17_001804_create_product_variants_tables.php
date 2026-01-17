<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de Talles (Solo si no existe)
        if (!Schema::hasTable('sizes')) {
            Schema::create('sizes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        // 2. Tabla de Colores (Solo si no existe)
        if (!Schema::hasTable('colors')) {
            Schema::create('colors', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('hex_code')->nullable();
                $table->timestamps();
            });
        }

        // 3. Modificar Tabla SKUS (Solo agregamos lo que falta)
        if (Schema::hasTable('skus')) {
            Schema::table('skus', function (Blueprint $table) {
                // Chequeamos columna por columna para no dar error
                if (!Schema::hasColumn('skus', 'size_id')) {
                     $table->foreignId('size_id')->nullable()->constrained();
                }
                if (!Schema::hasColumn('skus', 'color_id')) {
                     $table->foreignId('color_id')->nullable()->constrained();
                }
                // Aseguramos que tenga stock si no lo tenía
                if (!Schema::hasColumn('skus', 'stock_quantity')) {
                     $table->integer('stock_quantity')->default(0);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants_tables');
    }
};