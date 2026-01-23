<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('zones', function (Blueprint $table) {
            // Agregamos el código de zona si no existe
            if (!Schema::hasColumn('zones', 'code')) {
                $table->string('code')->nullable()->after('id');
            }
        });

        Schema::table('localities', function (Blueprint $table) {
            // Agregamos el código de localidad si no existe
            if (!Schema::hasColumn('localities', 'code')) {
                $table->string('code')->nullable()->after('id');
            }
        });
        
        Schema::table('clients', function (Blueprint $table) {
            // Aseguramos que la dirección sea TEXT por si el PDF tira basura larga
            $table->text('address')->nullable()->change();
        });
    }

    public function down(): void {}
};