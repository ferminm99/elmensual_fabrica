<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // 1. Borramos la regla incorrecta (que apunta a articles)
            // Usamos el nombre exacto que salió en tu error
            $table->dropForeign('order_items_sku_id_foreign');

            // 2. Creamos la regla correcta (que apunte a SKUS)
            $table->foreign('sku_id')
                  ->references('id')
                  ->on('skus')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Esto es solo por si quisiéramos volver al error (no hace falta)
    }
};