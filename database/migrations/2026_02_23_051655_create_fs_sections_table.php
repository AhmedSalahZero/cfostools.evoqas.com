<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_statement_id')->constrained('financial_statements')->onDelete('cascade');

            // Which of the 3 statements this section belongs to
            $table->enum('statement_type', ['income', 'balance_sheet', 'cashflow']);

            // Machine-readable key — used for ratio calculations
            // e.g. 'sales_revenue', 'cogs', 'marketing_expenses', 'current_assets' ...
            $table->string('section_key', 100);

            // Human-readable label shown on the form
            $table->string('display_name', 200);

            // Is this section auto-calculated (Gross Profit, EBITDA, Net Profit etc.)?
            // If yes, the frontend shows a read-only computed row instead of input
            $table->boolean('is_computed')->default(false);

            // Formula hint for computed rows — stored as JSON array of section_keys with +/- signs
            // Example: ["sales_revenue", "-cogs"] means sales_revenue minus cogs
            $table->json('computed_from')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['financial_statement_id', 'statement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_sections');
    }
};