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
        Schema::table('clients', function (Blueprint $table) {
            // Para el sistema de referidos (un cliente refiere a otro)
            if (!Schema::hasColumn('clients', 'referred_by_id')) {
                $table->foreignId('referred_by_id')->nullable()->constrained('clients')->nullOnDelete();
            }

            // Hacemos que price_list_id sea opcional por si no lo usás
            $table->foreignId('price_list_id')->nullable()->change();
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