<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            // Agregamos solo lo que te falta para el control de inventario
            if (!Schema::hasColumn('skus', 'stock_quantity')) {
                $table->integer('stock_quantity')->default(0);
            }
            if (!Schema::hasColumn('skus', 'min_stock')) {
                $table->integer('min_stock')->default(2);
            }
            if (!Schema::hasColumn('skus', 'code')) {
                $table->string('code')->nullable(); // Por si querés un código único por variante
            }
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'min_stock', 'code']);
        });
    }
};