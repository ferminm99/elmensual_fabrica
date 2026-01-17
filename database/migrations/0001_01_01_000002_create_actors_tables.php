<?php
// database/migrations/0001_01_01_000002_create_actors_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimal Suppliers table for FK constraints
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->nullable(); // CUIT
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('tax_id')->nullable()->index(); // CUIT
            $table->string('tax_condition')->nullable(); // e.g., Resp. Inscripto
            
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->foreignId('referred_by_id')->nullable()->constrained('clients')->nullOnDelete();
            
            $table->decimal('discount_fixed_percent', 5, 2)->default(0);
            
            // Geolocation
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            
            // Dual Ledger Balances
            $table->decimal('account_balance_fiscal', 15, 2)->default(0);
            $table->decimal('account_balance_internal', 15, 2)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
        Schema::dropIfExists('suppliers');
    }
};