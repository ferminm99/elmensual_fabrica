<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained(); // Qué producto
            $table->foreignId('user_id')->constrained(); // Quién lo hizo
            
            // Usamos Entry (Entrada) y Exit (Salida) para simplificar la matemática
            $table->enum('type', ['Entry', 'Exit']); 
            
            // El motivo es obligatorio para auditoría (ej: "Robo", "Devolución")
            $table->string('reason'); 
            
            $table->integer('quantity'); // Cantidad siempre positiva
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};