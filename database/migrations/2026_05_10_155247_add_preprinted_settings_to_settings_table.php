<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('use_preprinted_remito')->default(false);
            $table->decimal('preprinted_margin', 5, 2)->default(12.00); // 12 cm por defecto
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['use_preprinted_remito', 'preprinted_margin']);
        });
    }
};