<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Agregamos la columna article_id vinculada a la tabla articles
            // Usamos nullable() por si tenés datos viejos, y constrained() para la llave foránea
            $table->foreignId('article_id')
                  ->nullable()
                  ->after('order_id')
                  ->constrained('articles')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->dropColumn('article_id');
        });
    }
};