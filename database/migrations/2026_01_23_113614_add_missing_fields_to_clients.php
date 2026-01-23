<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('clients', function (Blueprint $table) {
            // Agregamos phone si no existe
            if (!Schema::hasColumn('clients', 'phone')) {
                $table->string('phone')->nullable();
            }
            // Agregamos address si no existe
            if (!Schema::hasColumn('clients', 'address')) {
                $table->string('address')->nullable();
            }
            // Hacemos que price_list_id sea opcional por las dudas
            $table->foreignId('price_list_id')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }
};