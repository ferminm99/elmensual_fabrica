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
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Ej: 01, 06
            $table->string('name');           // Ej: ESTE, SUR
            $table->timestamps();
        });

        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Ej: 0088, 0307
            $table->string('name');
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamps();
        });

        // Agregamos la relación al cliente
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('locality_id')->nullable()->constrained('localities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_tables');
    }
};