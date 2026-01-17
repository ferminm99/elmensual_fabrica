<?php
// database/migrations/0001_01_01_000005_create_sales_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            
            $table->string('status')->index(); // Pending, Completed, Canceled
            $table->string('payment_method')->nullable();
            
            // Dual Accounting Strategy
            $table->enum('origin', ['Fiscal', 'Internal'])->index();
            $table->enum('billing_strategy', ['Fiscal_A', 'Fiscal_B', 'Internal_X', 'Mixed']);
            
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained();
            
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            
            $table->string('cae_afip')->nullable(); // Null if it's draft or Internal invoice
            $table->enum('invoice_type', ['A', 'B', 'C', 'NC', 'ND']);
            $table->decimal('total_fiscal', 15, 2);
            
            // For Credit/Debit notes referencing a parent invoice
            $table->foreignId('parent_id')->nullable()->constrained('invoices');
            
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            // Assuming beneficiary is a User (sales rep) or Client (referral)
            // Using polymorphic or nullable FKs is common, here we default to users table (Employees)
            // Adjust to 'clients' if referrals are only clients.
            $table->unsignedBigInteger('beneficiary_id'); 
            
            $table->foreignId('source_order_id')->constrained('orders');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('Pending');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};