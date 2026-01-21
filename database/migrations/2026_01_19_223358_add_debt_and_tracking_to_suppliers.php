<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregamos deuda al Proveedor
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'fiscal_debt')) {
                $table->decimal('fiscal_debt', 15, 2)->default(0)->after('email');
                $table->decimal('internal_debt', 15, 2)->default(0)->after('fiscal_debt');
            }
        });

        // 2. Agregamos rastro al Cheque (¿A quién se lo dimos?)
        Schema::table('checks', function (Blueprint $table) {
            if (!Schema::hasColumn('checks', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete()->after('client_id');
                $table->timestamp('delivered_at')->nullable()->after('updated_at');
            }
        });

        // 3. Agregamos supplier_id a Transacciones (Para pagos en efectivo)
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete()->after('client_id');
            }
        });
    }

    public function down(): void
    {
        // ... (lógica inversa opcional)
    }
};