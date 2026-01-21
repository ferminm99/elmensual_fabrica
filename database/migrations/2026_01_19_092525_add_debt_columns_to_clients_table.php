<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Agregamos las columnas de deuda si no existen
            if (!Schema::hasColumn('clients', 'fiscal_debt')) {
                $table->decimal('fiscal_debt', 15, 2)->default(0)->after('name');
            }
            if (!Schema::hasColumn('clients', 'internal_debt')) {
                $table->decimal('internal_debt', 15, 2)->default(0)->after('fiscal_debt');
            }
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            //
        });
    }
};