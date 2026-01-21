<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            if (Schema::hasColumn('checks', 'due_date')) {
                $table->dropColumn('due_date');
            }

            // Opcional: Volver a poner payment_date como obligatoria (NOT NULL)
            // ya que ahora el formulario SÍ la manda.
            if (Schema::hasColumn('checks', 'payment_date')) {
                $table->date('payment_date')->nullable(false)->change();
            }
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            //
        });
    }
};