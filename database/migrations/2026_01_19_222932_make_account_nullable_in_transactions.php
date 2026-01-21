<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Hacemos que la cuenta sea opcional
            $table->foreignId('company_account_id')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};