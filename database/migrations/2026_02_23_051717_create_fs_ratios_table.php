<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_ratios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_statement_id')->constrained('financial_statements')->onDelete('cascade');

            // Group: profitability | liquidity | leverage | activity
            $table->string('ratio_group', 50);

            // Machine key: gross_margin_pct, ebitda_margin_pct, net_margin_pct,
            //              roa, roe, current_ratio, quick_ratio,
            //              debt_to_equity, debt_to_assets, interest_coverage,
            //              asset_turnover, receivables_turnover, inventory_turnover
            $table->string('ratio_key', 100);

            // Human-readable label shown in the UI
            $table->string('ratio_label', 200);

            // The calculated value — null if data was insufficient to calculate
            $table->decimal('ratio_value', 20, 6)->nullable();

            // For common-size: store each line item as % of revenue or total assets
            // We use a separate approach — see notes below

            $table->timestamps();

            $table->index(['financial_statement_id', 'ratio_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_ratios');
    }
};