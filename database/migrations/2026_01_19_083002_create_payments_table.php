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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // El pedido es opcional (nullable), porque pueden pagar "a cuenta"
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); 
            
            $table->decimal('amount', 15, 2);
            
            // ¿A qué deuda resta este pago?
            $table->enum('context', ['fiscal', 'informal'])->default('fiscal'); 
            
            // Forma de pago (Efectivo, Transferencia, Cheque)
            $table->string('method')->default('cash'); 
            
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};