<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('suppliers', function (Blueprint $table) {
        $table->decimal('account_balance_fiscal', 15, 2)->default(0);   // Deuda Blanca
        $table->decimal('account_balance_internal', 15, 2)->default(0); // Deuda Negra
    });
}

public function down(): void
{
    Schema::table('suppliers', function (Blueprint $table) {
        $table->dropColumn(['account_balance_fiscal', 'account_balance_internal']);
    });
}
};