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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('number')->nullable();      // Ej: 00010-00000001
            $table->date('cae_expiry')->nullable();    // Vencimiento del CAE
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices7', function (Blueprint $table) {
            //
        });
    }
};