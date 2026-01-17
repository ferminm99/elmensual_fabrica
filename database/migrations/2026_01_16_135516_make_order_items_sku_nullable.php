<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // 1. COMENTAMOS ESTA LÍNEA (Ponemos // adelante)
            // Porque el intento anterior ya la borró y por eso da error ahora.
            // $table->dropForeign(['sku_id']); 

            // 2. Hacemos la columna nullable
            $table->unsignedBigInteger('sku_id')->nullable()->change();

            // 3. Creamos la nueva restricción
            // IMPORTANTE: Si tu tabla de productos se llama 'articles', poné 'articles' abajo.
            // Si se llama 'skus', dejalo como 'skus'.
            $table->foreign('sku_id')
                  ->references('id')
                  ->on('articles') // <--- CAMBIÉ ESTO A 'articles' PORQUE CREO QUE ASÍ SE LLAMA TU TABLA AHORA
                  ->nullOnDelete(); 
        });
    }

    public function down(): void
    {
        // Revertir es difícil sin saber el nombre exacto anterior, pero lo dejamos indicado
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['sku_id']);
            $table->foreign('sku_id')->references('id')->on('skus');
        });
    }
};