<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_forecast_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')
                  ->constrained('portfolio_companies')
                  ->cascadeOnDelete();
            // Optionally linked to a specific financial statement
            $table->foreignId('financial_statement_id')
                  ->nullable()
                  ->constrained('financial_statements')
                  ->nullOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->enum('category', ['operating', 'investing', 'financing']);
            $table->string('description', 255);
            $table->decimal('amount', 20, 2);
            // YYYY-MM  e.g. "2026-02"
            $table->string('month', 7);
            $table->boolean('is_recurring')->default(false);
            // For recurring entries — stop repeating after this month (YYYY-MM)
            $table->string('recurring_end_month', 7)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['portfolio_company_id', 'month']);
            $table->index(['financial_statement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_forecast_entries');
    }
};