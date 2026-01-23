<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        // 1. Tabla para los Viajantes / Vendedores
        Schema::create('salespeople', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // El código "04"
            $table->string('name');           // El nombre "javier"
            $table->timestamps();
        });

        // 2. Agregar códigos a Zonas y Localidades
        Schema::table('zones', function (Blueprint $table) {
            if (!Schema::hasColumn('zones', 'code')) $table->string('code')->nullable()->after('id');
        });

        Schema::table('localities', function (Blueprint $table) {
            if (!Schema::hasColumn('localities', 'code')) $table->string('code')->nullable()->after('id');
        });

        // 3. Vincular el Cliente con el Viajante
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'salesperson_id')) {
                $table->foreignId('salesperson_id')->nullable()->constrained('salespeople')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};