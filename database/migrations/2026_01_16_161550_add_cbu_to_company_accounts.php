<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_accounts', function (Blueprint $table) {
            // Agregamos la columna CBU, que puede quedar vacía (nullable)
            // por si es una Caja Fuerte que no tiene CBU.
            $table->string('cbu')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('company_accounts', function (Blueprint $table) {
            $table->dropColumn('cbu');
        });
    }
};