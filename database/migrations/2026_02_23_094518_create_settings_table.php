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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('cai_number')->nullable();
            $table->date('cai_expiry')->nullable();
            $table->integer('remito_pv')->default(1);
            $table->integer('next_remito_number')->default(1);
            $table->timestamps();
        });

        // Insertamos el registro inicial con los datos de tu foto
        DB::table('settings')->insert([
            'cai_number' => '51504216196866',
            'cai_expiry' => '2026-12-12',
            'next_remito_number' => 59615, // El que sigue al de tu foto
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};