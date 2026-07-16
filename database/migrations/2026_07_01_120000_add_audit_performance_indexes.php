<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_company_notes', function (Blueprint $table) {
            $table->index('portfolio_company_id', 'pc_notes_company_idx');
        });

        Schema::table('budget_actuals', function (Blueprint $table) {
            $table->index('budget_line_item_id', 'budget_actuals_li_idx');
        });

        Schema::table('kpi_trackings', function (Blueprint $table) {
            $table->index(['company_id', 'period_label'], 'kpi_trackings_company_period_idx');
        });

        Schema::table('financial_statements', function (Blueprint $table) {
            $table->index(['portfolio_company_id', 'period_to'], 'fs_company_period_idx');
        });

        Schema::table('investadocs', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'investadocs_org_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_company_notes', function (Blueprint $table) {
            $table->dropIndex('pc_notes_company_idx');
        });

        Schema::table('budget_actuals', function (Blueprint $table) {
            $table->dropIndex('budget_actuals_li_idx');
        });

        Schema::table('kpi_trackings', function (Blueprint $table) {
            $table->dropIndex('kpi_trackings_company_period_idx');
        });

        Schema::table('financial_statements', function (Blueprint $table) {
            $table->dropIndex('fs_company_period_idx');
        });

        Schema::table('investadocs', function (Blueprint $table) {
            $table->dropIndex('investadocs_org_created_idx');
        });
    }
};
