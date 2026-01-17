<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            // Hacemos que las columnas viejas de texto sean opcionales
            // Usamos change() para modificar la columna existente
            $table->string('size')->nullable()->change();
            $table->string('color')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Si quisiéramos volver atrás (no hará falta)
        Schema::table('skus', function (Blueprint $table) {
            $table->string('size')->nullable(false)->change();
            $table->string('color')->nullable(false)->change();
        });
    }
};