<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiamos la columna 'unit' para que sea texto libre (hasta 50 letras)
        DB::statement("ALTER TABLE raw_materials MODIFY COLUMN unit VARCHAR(50) NOT NULL DEFAULT 'mts'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            //
        });
    }
};