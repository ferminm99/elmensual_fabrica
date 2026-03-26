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
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            // Lo atamos a TUS cuentas bancarias reales (Galicia, Provincia, etc.)
            $table->foreignId('company_account_id')->constrained('company_accounts')->cascadeOnDelete();
            $table->date('statement_date')->nullable(); // Fecha del extracto
            $table->string('file_path')->nullable(); // Donde guardamos el excel
            $table->enum('status', ['pending', 'processed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};