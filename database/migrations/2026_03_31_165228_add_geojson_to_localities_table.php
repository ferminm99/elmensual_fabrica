<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('localities', function (Blueprint $table) {
            // Usamos json para guardar todo el mapa del borde de la ciudad
            $table->json('geojson')->nullable()->after('lng'); 
        });
    }

    public function down(): void
    {
        Schema::table('localities', function (Blueprint $table) {
            $table->dropColumn('geojson');
        });
    }
};