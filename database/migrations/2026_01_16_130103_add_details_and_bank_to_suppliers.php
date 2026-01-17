<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // 1. Campos de contacto (Los que te daban error)
            if (!Schema::hasColumn('suppliers', 'email')) $table->string('email')->nullable();
            if (!Schema::hasColumn('suppliers', 'phone')) $table->string('phone')->nullable();
            if (!Schema::hasColumn('suppliers', 'address')) $table->string('address')->nullable();
            if (!Schema::hasColumn('suppliers', 'tax_id')) $table->string('tax_id')->nullable(); // CUIT

            // 2. Datos Bancarios (Lo nuevo que pediste)
            $table->string('bank_name')->nullable(); // Ej: Banco Nación
            $table->string('cbu')->nullable();       // CBU / CVU
            $table->string('alias')->nullable();     // Alias
            $table->string('account_number')->nullable(); // Nro Cuenta
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'address', 'tax_id', 'bank_name', 'cbu', 'alias', 'account_number']);
        });
    }
};