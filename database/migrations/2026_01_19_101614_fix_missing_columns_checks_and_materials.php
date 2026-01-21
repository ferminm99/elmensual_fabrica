<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ARREGLO DE CHEQUES (Faltaba el 'owner')
        Schema::table('checks', function (Blueprint $table) {
            if (!Schema::hasColumn('checks', 'owner')) {
                $table->string('owner')->nullable()->after('number'); // Firmante del cheque
            }
        });

        // 2. ARREGLO DE MATERIA PRIMA (Faltaba el stock y el color)
        Schema::table('raw_materials', function (Blueprint $table) {
            
            // Si te faltaba la cantidad de stock
            if (!Schema::hasColumn('raw_materials', 'stock_quantity')) {
                $table->decimal('stock_quantity', 10, 2)->default(0)->after('name');
            }

            // Si te faltaba el link al color (porque tu tabla usa color.name)
            if (!Schema::hasColumn('raw_materials', 'color_id')) {
                $table->foreignId('color_id')->nullable()->constrained()->nullOnDelete()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('owner');
        });
        
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'color_id']);
        });
    }
};