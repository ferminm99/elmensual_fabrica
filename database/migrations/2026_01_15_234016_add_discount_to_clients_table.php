<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Creamos la columna sin decirle "después de quién", para que no falle.
            // Se agregará al final de la tabla.
            $table->decimal('default_discount', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('default_discount');
        });
    }
};