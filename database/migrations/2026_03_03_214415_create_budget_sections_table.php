<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_statement_id')->constrained()->cascadeOnDelete();

            // statement_type: income | balance_sheet | cashflow
            $table->string('statement_type', 30);

            // Fixed section key (sales_revenue, cogs, gross_profit, etc.)
            $table->string('section_key', 60);

            $table->string('display_name', 120);

            // Computed rows (Gross Profit, EBITDA, etc.) — no user input allowed
            $table->boolean('is_computed')->default(false);

            // JSON array of [{key, sign}] pairs for formula evaluation
            $table->json('computed_from')->nullable();

            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_sections');
    }
};