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
        Schema::table('company_accounts', function (Blueprint $table) {
            // Aseguramos que 'type' sea un string de 50 caracteres y acepte nulos por si acaso
            $table->string('type', 50)->nullable()->change();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_accounts', function (Blueprint $table) {
            //
        });
    }
};