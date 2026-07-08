<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')->constrained('portfolio_companies')->onDelete('cascade');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('currency', 10);          // Copied from company base_currency at time of entry
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            // One statement per company per period
            $table->unique(['portfolio_company_id', 'period_from', 'period_to'], 'unique_statement_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_statements');
    }
};