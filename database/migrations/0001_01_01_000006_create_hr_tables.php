<?php
// database/migrations/0001_01_01_000006_create_hr_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cuil')->unique();
            $table->string('cbu')->nullable();
            $table->decimal('salary_base', 15, 2);
            $table->timestamps();
        });

        Schema::create('salary_periods', function (Blueprint $table) {
            $table->id();
            $table->integer('month');
            $table->integer('year');
            $table->string('status')->default('Open'); // Open, Closed, Paid
            $table->timestamps();
            
            $table->unique(['month', 'year']);
        });

        Schema::create('salary_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('salary_periods');
            $table->foreignId('employee_id')->constrained('employees');
            
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->string('pdf_path')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_settlements');
        Schema::dropIfExists('salary_periods');
        Schema::dropIfExists('employees');
    }
};