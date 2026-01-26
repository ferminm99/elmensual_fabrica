<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        // Solo agregamos el que falta
        $table->enum('billing_status', ['pending', 'invoiced', 'reversal_required', 'credited'])
              ->default('pending')
              ->after('billing_type');
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropColumn('billing_status');
    });
}
};