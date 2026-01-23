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
        // Tabla de Viajantes
        Schema::create('salesmen', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // El "04" del PDF
            $table->string('name');           // El "javier"
            $table->string('dni')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        // Tabla Pivot: Zonas asignadas a cada Viajante
        Schema::create('salesman_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
        });

        // Vincular Cliente con su Viajante
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'salesman_id')) {
                $table->foreignId('salesman_id')->nullable()->after('locality_id')->constrained('salesmen')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesmen_and_relations_tables');
    }
};