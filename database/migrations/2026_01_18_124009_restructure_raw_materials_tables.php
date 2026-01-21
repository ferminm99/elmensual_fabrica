<?php

// database/migrations/xxxx_xx_xx_restructure_raw_materials_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. LIMPIAR LA TABLA PADRE (Sacamos color y stock de acá)
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropForeign(['color_id']);
            $table->dropColumn(['color_id', 'stock_quantity']);
            // El 'cost_per_unit' lo dejamos como "Costo de Referencia" para recetas
        });

        // 2. TABLA DE STOCK POR COLOR (El Inventario Real)
        Schema::create('raw_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained(); // Tu tabla de colores
            $table->decimal('quantity', 10, 2)->default(0); // Cantidad (mts/kg)
            $table->string('location')->nullable(); // Ej: "Estantería A"
            $table->timestamps();
        });

        // 3. TABLA DE PRECIOS POR PROVEEDOR (La Comparativa)
        Schema::create('material_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete(); // Tu tabla Suppliers
            $table->decimal('price', 10, 2); // El precio que te hace ESTE proveedor
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_supplier');
        Schema::dropIfExists('raw_material_stocks');
        // (La vuelta atrás de raw_materials es compleja, simplificamos aquí)
    }
};