<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hacemos que la columna vieja sea opcional en las cuentas de proveedores
        Schema::table('supplier_bank_accounts', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->change();
        });

        // Hacemos lo mismo en los cheques por si acaso
        Schema::table('checks', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bank_accounts', function (Blueprint $table) {
            $table->string('bank_name')->nullable(false)->change();
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->string('bank_name')->nullable(false)->change();
        });
    }
};