<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            // Eliminamos las columnas viejas y rígidas
            if (Schema::hasColumn('production_orders', 'raw_material_id')) {
                $table->dropForeign(['raw_material_id']);
                $table->dropColumn('raw_material_id');
            }
            if (Schema::hasColumn('production_orders', 'article_id')) {
                $table->dropForeign(['article_id']);
                $table->dropColumn('article_id');
            }
            if (Schema::hasColumn('production_orders', 'color_id')) {
                $table->dropForeign(['color_id']);
                $table->dropColumn('color_id');
            }
            if (Schema::hasColumn('production_orders', 'usage_quantity')) {
                $table->dropColumn('usage_quantity');
            }

            // Agregamos las nuevas columnas flexibles tipo JSON
            $table->json('article_ids')->nullable();
            $table->json('used_materials')->nullable();
            $table->json('article_groups')->nullable();
            $table->json('color_ids')->nullable();
        });
    }
};