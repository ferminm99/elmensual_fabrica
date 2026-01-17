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
        Schema::table('stock_movements', function (Blueprint $table) {
            // 1. Borramos la restricción vieja que bloquea el borrado
            // El nombre lo saqué textualmente de tu imagen de error
            $table->dropForeign('stock_movements_sku_id_foreign');

            // 2. Hacemos la columna nullable (para que acepte quedar vacía)
            $table->unsignedBigInteger('sku_id')->nullable()->change();

            // 3. Creamos la nueva restricción flexible
            // Ahora si borras el artículo, el historial queda pero con sku_id = NULL
            $table->foreign('sku_id')
                  ->references('id')
                  ->on('articles') // Apuntamos a la tabla correcta 'articles'
                  ->nullOnDelete(); 
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            //
        });
    }
};