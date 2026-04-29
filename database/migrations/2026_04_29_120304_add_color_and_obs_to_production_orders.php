<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->integer('expected_quantity')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete(); // NUEVO: Color de la tela
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            //
        });
    }
};