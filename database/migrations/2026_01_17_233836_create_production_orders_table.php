<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. La Cabecera: ¿Qué tela cortamos?
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Ej: CORTE-0001
            
            // Relación con la TELA que gastamos
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            
            // Relación con el ARTÍCULO que queríamos hacer
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            
            $table->decimal('usage_quantity', 10, 2); // Cuántos metros gastamos (Ej: 100)
            
            $table->enum('status', ['pendiente', 'finalizado'])->default('pendiente');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. El Detalle: ¿Qué talles salieron?
        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            
            // Aquí guardamos la variante exacta (Ej: Bombacha Talle 36 - Negra)
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            
            $table->integer('quantity'); // Salieron 20 de este talle
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};