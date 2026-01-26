<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Logística
            $table->integer('priority')->default(1); // 0: Baja, 1: Normal, 2: Alta, 3: Urgente

            // Facturación ("El Bardo")
            $table->string('invoice_number')->nullable(); // Número de Factura
            $table->string('credit_note_number')->nullable(); // Nota de Crédito (si se refactura)
            $table->dateTime('invoiced_at')->nullable(); // Fecha de facturación
            $table->dateTime('delivered_at')->nullable(); // Fecha real de entrega (cierre)
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['priority', 'invoice_number', 'credit_note_number', 'invoiced_at', 'delivered_at']);
        });
    }
};