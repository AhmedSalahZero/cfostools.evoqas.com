<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KpiDefinition;

class KpiDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $kpis = [

            // ── Income Statement ──────────────────────────────────────────
            ['name' => 'Revenue',              'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'revenue',              'higher_is_better' => true,  'sort_order' => 1],
            ['name' => 'Cost of Goods Sold',   'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'cogs',                 'higher_is_better' => false, 'sort_order' => 2],
            ['name' => 'Gross Profit',         'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'gross_profit',         'higher_is_better' => true,  'sort_order' => 3],
            ['name' => 'Gross Margin %',       'category' => 'financial', 'unit' => 'percentage', 'source' => 'auto_fs', 'fs_mapping' => 'gross_margin_pct',     'higher_is_better' => true,  'sort_order' => 4],
            ['name' => 'EBITDA',               'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'ebitda',               'higher_is_better' => true,  'sort_order' => 5],
            ['name' => 'EBITDA Margin %',      'category' => 'financial', 'unit' => 'percentage', 'source' => 'auto_fs', 'fs_mapping' => 'ebitda_margin_pct',    'higher_is_better' => true,  'sort_order' => 6],
            ['name' => 'Net Income',           'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'net_income',           'higher_is_better' => true,  'sort_order' => 7],
            ['name' => 'Net Profit Margin %',  'category' => 'financial', 'unit' => 'percentage', 'source' => 'auto_fs', 'fs_mapping' => 'ratio_net_margin',     'higher_is_better' => true,  'sort_order' => 8],

            // ── Cash Flow ─────────────────────────────────────────────────
            ['name' => 'Operating Cash Flow',  'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'operating_cf',         'higher_is_better' => true,  'sort_order' => 9],
            ['name' => 'Free Cash Flow',       'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'free_cash_flow',       'higher_is_better' => true,  'sort_order' => 10],
            ['name' => 'Cash & Equivalents',   'category' => 'financial', 'unit' => 'currency',   'source' => 'auto_fs', 'fs_mapping' => 'cash_equivalents',     'higher_is_better' => true,  'sort_order' => 11],

            // ── Liquidity Ratios ──────────────────────────────────────────
            ['name' => 'Current Ratio',        'category' => 'financial', 'unit' => 'ratio',      'source' => 'auto_fs', 'fs_mapping' => 'ratio_current_ratio',  'higher_is_better' => true,  'sort_order' => 12],
            ['name' => 'Quick Ratio',          'category' => 'financial', 'unit' => 'ratio',      'source' => 'auto_fs', 'fs_mapping' => 'ratio_quick_ratio',    'higher_is_better' => true,  'sort_order' => 13],

            // ── Profitability Ratios ──────────────────────────────────────
            ['name' => 'Return on Equity (ROE)', 'category' => 'financial', 'unit' => 'percentage', 'source' => 'auto_fs', 'fs_mapping' => 'ratio_roe',          'higher_is_better' => true,  'sort_order' => 14],
            ['name' => 'Return on Assets (ROA)', 'category' => 'financial', 'unit' => 'percentage', 'source' => 'auto_fs', 'fs_mapping' => 'ratio_roa',          'higher_is_better' => true,  'sort_order' => 15],

            // ── Leverage Ratios ───────────────────────────────────────────
            ['name' => 'Debt to Equity',       'category' => 'financial', 'unit' => 'ratio',      'source' => 'auto_fs', 'fs_mapping' => 'ratio_debt_to_equity', 'higher_is_better' => false, 'sort_order' => 16],
            ['name' => 'Interest Coverage',    'category' => 'financial', 'unit' => 'ratio',      'source' => 'auto_fs', 'fs_mapping' => 'ratio_interest_coverage', 'higher_is_better' => true, 'sort_order' => 17],

            // ── Working Capital / Efficiency ──────────────────────────────
            ['name' => 'DSO (Days Sales Outstanding)',    'category' => 'financial', 'unit' => 'number', 'source' => 'auto_fs', 'fs_mapping' => 'ratio_dso', 'higher_is_better' => false, 'sort_order' => 18],
            ['name' => 'DIO (Days Inventory Outstanding)','category' => 'financial', 'unit' => 'number', 'source' => 'auto_fs', 'fs_mapping' => 'ratio_dio', 'higher_is_better' => false, 'sort_order' => 19],
            ['name' => 'DPO (Days Payable Outstanding)',  'category' => 'financial', 'unit' => 'number', 'source' => 'auto_fs', 'fs_mapping' => 'ratio_dpo', 'higher_is_better' => true,  'sort_order' => 20],

            // ── Non-Financial ─────────────────────────────────────────────
            ['name' => 'Headcount',            'category' => 'non_financial', 'unit' => 'number',     'source' => 'manual', 'fs_mapping' => null, 'higher_is_better' => true,  'sort_order' => 21],
            ['name' => 'Customer Count',       'category' => 'non_financial', 'unit' => 'number',     'source' => 'manual', 'fs_mapping' => null, 'higher_is_better' => true,  'sort_order' => 22],
            ['name' => 'Churn Rate %',         'category' => 'non_financial', 'unit' => 'percentage', 'source' => 'manual', 'fs_mapping' => null, 'higher_is_better' => false, 'sort_order' => 23],
            ['name' => 'NPS Score',            'category' => 'non_financial', 'unit' => 'number',     'source' => 'manual', 'fs_mapping' => null, 'higher_is_better' => true,  'sort_order' => 24],
            ['name' => 'Units Sold',           'category' => 'non_financial', 'unit' => 'number',     'source' => 'manual', 'fs_mapping' => null, 'higher_is_better' => true,  'sort_order' => 25],
            ['name' => 'Market Share %',       'category' => 'non_financial', 'unit' => 'percentage', 'source' => 'manual', 'fs_mapping' => null, 'higher_is_better' => true,  'sort_order' => 26],
        ];

        foreach ($kpis as $kpi) {
            KpiDefinition::firstOrCreate(
                [
                    'organization_id' => null,
                    'name'            => $kpi['name'],
                ],
                array_merge($kpi, ['organization_id' => null, 'is_active' => true, 'description' => null])
            );
        }
    }
}