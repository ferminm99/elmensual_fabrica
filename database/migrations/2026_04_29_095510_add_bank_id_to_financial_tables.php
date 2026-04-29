<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le agregamos la columna bank_id a las cuentas de los proveedores
        Schema::table('supplier_bank_accounts', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('supplier_id')->constrained('banks')->nullOnDelete();
        });

        // Le agregamos la columna bank_id a los cheques (para poder emitir cheques propios correctamente)
        Schema::table('checks', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('supplier_id')->constrained('banks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
        });
    }
};