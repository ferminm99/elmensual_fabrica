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
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->string('invoice_number'); // Ej: 0001-00001234
            $table->date('date');
            
            // Totales y Desglose
            $table->decimal('net_amount', 15, 2)->default(0); // Neto Gravado
            $table->decimal('iva_amount', 15, 2)->default(0); // IVA (21, 10.5, etc)
            $table->decimal('non_taxed_amount', 15, 2)->default(0); // Conceptos No Gravados
            $table->decimal('exempt_amount', 15, 2)->default(0); // Operaciones Exentas
            
            // Impuestos Adicionales
            $table->decimal('retention_amount', 15, 2)->default(0); // Retenciones (Ganancias/IVA)
            $table->decimal('perception_amount', 15, 2)->default(0); // Percepciones (IVA/IIBB)
            $table->decimal('iibb_amount', 15, 2)->default(0); // Impuesto IIBB directo
            
            $table->decimal('total_amount', 15, 2); // El total final de la factura
            
            $table->string('status')->default('pending'); // pending, paid, partial
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
