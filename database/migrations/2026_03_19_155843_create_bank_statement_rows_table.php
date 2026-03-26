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
        Schema::create('bank_statement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            
            // Datos crudos que vienen del banco
            $table->date('date');
            $table->string('concept')->nullable();
            $table->string('reference')->nullable();
            $table->string('cuit_origin')->nullable(); // El CUIT del que te transfirió (CLAVE)
            $table->string('name_origin')->nullable(); // El Nombre que tiró el banco
            $table->decimal('amount', 12, 2); // Monto
            
            // Estado de la conciliación
            $table->enum('status', ['pending', 'approved', 'ignored'])->default('pending');
            
            // MAGIA: Si el sistema reconoce el CUIT, lo ata automáticamente a TU tabla de clientes
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete(); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statement_rows');
    }
};