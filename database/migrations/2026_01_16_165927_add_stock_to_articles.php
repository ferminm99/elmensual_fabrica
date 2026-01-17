<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0); // Cantidad actual
            $table->integer('min_stock')->default(5);      // Alerta de stock bajo
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'min_stock']);
        });
    }
};