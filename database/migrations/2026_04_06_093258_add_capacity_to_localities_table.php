<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('localities', function (Blueprint $table) {
            $table->integer('client_capacity')->default(3)->after('name');
        });
    }
    public function down(): void {
        Schema::table('localities', function (Blueprint $table) {
            $table->dropColumn('client_capacity');
        });
    }
};