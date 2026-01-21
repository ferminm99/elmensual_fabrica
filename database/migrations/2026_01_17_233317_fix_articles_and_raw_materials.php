<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Arreglamos ARTÍCULOS (Agregamos el consumo)
        Schema::table('articles', function (Blueprint $table) {
            // Si no existe la columna, la creamos
            if (!Schema::hasColumn('articles', 'average_consumption')) {
                $table->decimal('average_consumption', 10, 2)->nullable()->after('base_cost');
            }
        });

        // 2. Arreglamos MATERIAS PRIMAS (Cambiamos Texto por ID de Color)
        Schema::table('raw_materials', function (Blueprint $table) {
            // Borramos la columna vieja de texto si existe
            if (Schema::hasColumn('raw_materials', 'color')) {
                $table->dropColumn('color');
            }
            // Agregamos la conexión con la tabla de Colores
            if (!Schema::hasColumn('raw_materials', 'color_id')) {
                $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            }
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};