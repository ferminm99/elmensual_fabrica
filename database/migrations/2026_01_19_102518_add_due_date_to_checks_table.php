<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            if (!Schema::hasColumn('checks', 'due_date')) {
                // Agregamos la fecha de cobro
                $table->date('due_date')->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};