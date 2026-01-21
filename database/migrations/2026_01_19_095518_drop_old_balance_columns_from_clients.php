<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Borramos las columnas viejas SI existen
            if (Schema::hasColumn('clients', 'account_balance_fiscal')) {
                $table->dropColumn('account_balance_fiscal');
            }
            if (Schema::hasColumn('clients', 'account_balance_internal')) {
                $table->dropColumn('account_balance_internal');
            }
            // También la de 'real_balance' si existía como columna física (a veces pasa)
            if (Schema::hasColumn('clients', 'real_balance')) {
                $table->dropColumn('real_balance');
            }
        });
    }

    public function down(): void
    {
        // Si quisiéramos volver atrás (opcional)
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('account_balance_fiscal', 15, 2)->default(0);
            $table->decimal('account_balance_internal', 15, 2)->default(0);
        });
    }
};