<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            // 1. Agregar 'description' si no existe (El error que te dio)
            if (!Schema::hasColumn('transactions', 'description')) {
                $table->string('description')->nullable()->after('amount');
            }

            // 2. Agregar 'origin' si no existe (Para saber si es Fiscal o Negro)
            if (!Schema::hasColumn('transactions', 'origin')) {
                $table->enum('origin', ['Fiscal', 'Internal'])->default('Internal')->after('type');
            }

            // 3. Asegurar que 'payment_details' exista (Para el CBU/Banco)
            if (!Schema::hasColumn('transactions', 'payment_details')) {
                $table->json('payment_details')->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // No borramos nada para no perder datos en un rollback accidental
        });
    }
};