<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Fix the business_type ENUM to include 'mixed' ──
        // MySQL requires re-declaring the full enum to add a value
        DB::statement("ALTER TABLE financial_studies MODIFY COLUMN business_type ENUM('manufacturing','trading','service','mixed') NOT NULL DEFAULT 'manufacturing'");

        // ── Step 2: Add one JSON column per wizard step ──
        Schema::table('financial_studies', function (Blueprint $table) {

            // Step 2 — Sales Projection
            // Stores: revenue plan per product (volume, price, seasonality,
            //         local/export split, channels, collection policy)
            $table->json('sales_data')->nullable()->after('products');

            // Step 3 — COGS
            // Manufacturing: BOM (raw material qty × unit cost per product)
            // Trading: purchase cost % or unit cost + inventory days
            // Service: direct cost % per product
            $table->json('cogs_data')->nullable()->after('sales_data');

            // Step 4 — Operating Expenses
            // % of revenue, fixed monthly, cost per unit, one-time (amortised)
            $table->json('expenses_data')->nullable()->after('cogs_data');

            // Step 5 — Manpower
            // Headcount plan by department, salary, hire date, annual increase %
            $table->json('manpower_data')->nullable()->after('expenses_data');

            // Step 6 — Fixed Assets & CAPEX
            // Asset name, cost, depreciation years, equity/debt split,
            // loan terms (interest rate, tenor, grace period)
            $table->json('capex_data')->nullable()->after('manpower_data');

            // Step 7 — Opening Balance
            // Only used when new_company = false (existing company)
            // Assets, liabilities, equity at study start date
            $table->json('opening_balance')->nullable()->after('capex_data');

            // Step 8 — Sensitivity Analysis inputs
            // Variables to stress-test: revenue %, cost %, WACC shifts
            $table->json('sensitivity_data')->nullable()->after('opening_balance');

        });
    }

    public function down(): void
    {
        // Revert enum
        DB::statement("ALTER TABLE financial_studies MODIFY COLUMN business_type ENUM('manufacturing','trading','service') NOT NULL DEFAULT 'manufacturing'");

        Schema::table('financial_studies', function (Blueprint $table) {
            $table->dropColumn([
                'sales_data',
                'cogs_data',
                'expenses_data',
                'manpower_data',
                'capex_data',
                'opening_balance',
                'sensitivity_data',
            ]);
        });
    }
};