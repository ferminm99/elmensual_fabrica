<?php
// database/migrations/0001_01_01_000003_create_treasury_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['Bank', 'Cash', 'Wallet'])->index();
            $table->enum('currency', ['ARS', 'USD'])->default('ARS');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->string('number')->index();
            $table->string('bank_name');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->index();
            
            $table->enum('type', ['Own', 'ThirdParty'])->index();
            $table->enum('status', ['InPortfolio', 'Deposited', 'Delivered', 'Rejected'])->default('InPortfolio')->index();
            
            $table->foreignId('received_from_client_id')->nullable()->constrained('clients');
            $table->foreignId('delivered_to_supplier_id')->nullable()->constrained('suppliers');
            
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_account_id')->constrained();
            
            // Dual Accounting Logic
            $table->enum('origin', ['Fiscal', 'Internal'])->index(); 
            
            $table->enum('type', ['Income', 'Outcome'])->index();
            $table->string('concept');
            $table->decimal('amount', 15, 2);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('checks');
        Schema::dropIfExists('company_accounts');
    }
};