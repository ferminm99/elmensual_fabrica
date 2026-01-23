<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('localities', function (Blueprint $table) {
            // Permitimos que zone_id sea nulo
            $table->foreignId('zone_id')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('localities', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable(false)->change();
        });
    }
};