<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            
            // Datos del comprobante
            $table->string('tipo_comprobante')->nullable();
            $table->string('numero')->nullable();
            $table->date('fecha_emision')->nullable();
            
            // Pruebas y validación (AFIP)
            $table->string('cae')->nullable();
            $table->string('attachment')->nullable();
            
            // Desglose de Impuestos (Libro IVA)
            $table->decimal('neto_gravado', 15, 2)->default(0);
            $table->decimal('no_gravado', 15, 2)->default(0);
            $table->decimal('exento', 15, 2)->default(0);
            $table->decimal('iva', 15, 2)->default(0);
            $table->decimal('perc_iva', 15, 2)->default(0);
            $table->decimal('perc_iibb', 15, 2)->default(0);
            $table->decimal('perc_imp_internos', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};