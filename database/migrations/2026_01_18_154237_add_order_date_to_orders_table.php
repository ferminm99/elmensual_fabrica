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
        Schema::table('orders', function (Blueprint $table) {
            // Agregamos la columna order_date, puede ser nula por si acaso
            $table->date('order_date')->nullable()->after('status'); 
            
            // Si tampoco tenías el total_amount (monto total), agregalo de paso:
            // $table->decimal('total_amount', 10, 2)->default(0)->after('order_date');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};