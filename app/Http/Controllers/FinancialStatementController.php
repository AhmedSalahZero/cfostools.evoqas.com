<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\PortfolioCompany;
use App\Models\FinancialStatement;
use App\Models\FsSection;
use App\Models\FsLineItem;
use App\Models\FsSettlementSchedule;
use App\Models\FsRatio;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FinancialStatementExport;

class FinancialStatementController extends Controller
{
    private function ensureFsAccess(PortfolioCompany $company): void
    {
        $this->authorizeCompany($company, 'financial_statements');
    }

    // ─────────────────────────────────────────────
    // Predefined section templates per statement type
    // is_computed = true  →  frontend shows a read-only calculated row
    // computed_from       →  array of [key, sign] pairs used in JS and PHP
    // ─────────────────────────────────────────────
    private function incomeSections(): array
    {
        return [
            ['key' => 'sales_revenue',        'label' => 'Sales Revenues',                    'computed' => false, 'from' => null,                                                                  'sort' => 1],
            ['key' => 'cogs',                  'label' => 'Cost of Goods Sold / Cost of Service','computed' => false, 'from' => null,                                                               'sort' => 2],
            ['key' => 'gross_profit',          'label' => 'Gross Profit',                      'computed' => true,  'from' => [['key'=>'sales_revenue','sign'=>1],['key'=>'cogs','sign'=>-1]],       'sort' => 3],
            ['key' => 'marketing_expenses',    'label' => 'Marketing & Sales Expenses',        'computed' => false, 'from' => null,                                                                  'sort' => 4],
            ['key' => 'ga_expenses',           'label' => 'General & Administrative Expenses', 'computed' => false, 'from' => null,                                                                  'sort' => 5],
            ['key' => 'ebitda',                'label' => 'EBITDA',                            'computed' => true,  'from' => [['key'=>'gross_profit','sign'=>1],['key'=>'marketing_expenses','sign'=>-1],['key'=>'ga_expenses','sign'=>-1]], 'sort' => 6],
            ['key' => 'depreciation',          'label' => 'Depreciation & Amortization',      'computed' => false, 'from' => null,                                                                  'sort' => 7],
            ['key' => 'ebit',                  'label' => 'EBIT',                              'computed' => true,  'from' => [['key'=>'ebitda','sign'=>1],['key'=>'depreciation','sign'=>-1]],      'sort' => 8],
            ['key' => 'finance_income_expense','label' => 'Finance Income & (Expenses)',       'computed' => false, 'from' => null,                                                                  'sort' => 9],
            ['key' => 'ebt',                   'label' => 'Earnings Before Tax (EBT)',         'computed' => true,  'from' => [['key'=>'ebit','sign'=>1],['key'=>'finance_income_expense','sign'=>1]],'sort' => 10],
            ['key' => 'taxes',                 'label' => 'Income Taxes',                      'computed' => false, 'from' => null,                                                                  'sort' => 11],
            ['key' => 'net_profit',            'label' => 'Net Profit',                        'computed' => true,  'from' => [['key'=>'ebt','sign'=>1],['key'=>'taxes','sign'=>-1]],                'sort' => 12],
        ];

    }

    private function balanceSections(): array
    {
        return [
            ['key' => 'current_assets',        'label' => 'Current Assets',              'computed' => false, 'from' => null,                                                                              'sort' => 1],
            ['key' => 'non_current_assets',     'label' => 'Non-Current Assets',         'computed' => false, 'from' => null,                                                                              'sort' => 2],
            ['key' => 'total_assets',           'label' => 'Total Assets',               'computed' => true,  'from' => [['key'=>'current_assets','sign'=>1],['key'=>'non_current_assets','sign'=>1]],      'sort' => 3],
            ['key' => 'current_liabilities',    'label' => 'Current Liabilities',        'computed' => false, 'from' => null,                                                                              'sort' => 4],
            ['key' => 'non_current_liabilities','label' => 'Non-Current Liabilities',    'computed' => false, 'from' => null,                                                                              'sort' => 5],
            ['key' => 'total_liabilities',      'label' => 'Total Liabilities',          'computed' => true,  'from' => [['key'=>'current_liabilities','sign'=>1],['key'=>'non_current_liabilities','sign'=>1]], 'sort' => 6],
            ['key' => 'equity',                 'label' => 'Shareholders\' Equity',      'computed' => false, 'from' => null,                                                                              'sort' => 7],
            ['key' => 'total_equity',           'label' => 'Total Equity',               'computed' => true,  'from' => [['key'=>'equity','sign'=>1]],                                                     'sort' => 8],
            ['key' => 'total_liabilities_equity','label' => 'Total Liabilities & Equity','computed' => true,  'from' => [['key'=>'total_liabilities','sign'=>1],['key'=>'total_equity','sign'=>1]],         'sort' => 9],
        ];
    }

    private function cashflowSections(): array
    {
        return [
            ['key' => 'cfo',         'label' => 'Cash from Operating Activities', 'computed' => false, 'from' => null,                                                                    'sort' => 1],
            ['key' => 'cfi',         'label' => 'Cash from Investing Activities', 'computed' => false, 'from' => null,                                                                    'sort' => 2],
            ['key' => 'cff',         'label' => 'Cash from Financing Activities', 'computed' => false, 'from' => null,                                                                    'sort' => 3],
            ['key' => 'net_cash',    'label' => 'Net Change in Cash',             'computed' => true,  'from' => [['key'=>'cfo','sign'=>1],['key'=>'cfi','sign'=>1],['key'=>'cff','sign'=>1]], 'sort' => 4],
        ];
    }

    // ─────────────────────────────────────────────
    // INDEX — list all statements for a company
    // ─────────────────────────────────────────────
    public function index(PortfolioCompany $company)
    {
        $this->ensureFsAccess($company);
        $statements = FinancialStatement::where('portfolio_company_id', $company->id)
            ->orderByDesc('period_to')
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'period_from' => $s->period_from,
                'period_to'   => $s->period_to,
                'currency'    => $s->currency,
                'status'      => $s->status,
                'created_at'  => $s->created_at->format('d M Y'),
            ]);

        return Inertia::render('FinancialStatements/Index', [
            'company'    => ['id' => $company->id, 'name' => $company->name],
            'statements' => $statements,
        ]);
    }

    // ─────────────────────────────────────────────
    // CREATE — show blank form
    // ─────────────────────────────────────────────
    public function create(PortfolioCompany $company)
    {
        $this->ensureFsAccess($company);
        return Inertia::render('FinancialStatements/Create', [
            'company'          => ['id' => $company->id, 'name' => $company->name, 'currency' => $company->invested_currency ?? 'USD'],
            'incomeSections'   => $this->incomeSections(),
            'balanceSections'  => $this->balanceSections(),
            'cashflowSections' => $this->cashflowSections(),
        ]);
    }

    // ─────────────────────────────────────────────
    // STORE — save new statement
    // ─────────────────────────────────────────────
    public function store(Request $request, PortfolioCompany $company)
    {
        $this->ensureFsAccess($company);
        $request->validate([
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after:period_from',
            'status'      => 'required|in:draft,final',
            'notes'       => 'nullable|string|max:2000',
            'sections'    => 'required|array',
        ]);

        DB::transaction(function () use ($request, $company) {
            $statement = FinancialStatement::create([
                'portfolio_company_id' => $company->id,
                'period_from'          => $request->period_from,
                'period_to'            => $request->period_to,
                'currency'             => $company->invested_currency ?? 'USD',
                'status'               => $request->status,
                'notes'                => $request->notes,
                'created_by'           => auth()->id(),
            ]);

            $this->saveSections($statement, $request->sections);
            $this->calculateAndSaveRatios($statement);
        });

        

        return redirect()
            ->route('financial-statements.index', $company->id)
            ->with('success', 'Financial statement saved successfully.');
    }

    

    // ─────────────────────────────────────────────
    // EDIT — load existing statement into form
    // ─────────────────────────────────────────────
    public function edit(PortfolioCompany $company, FinancialStatement $statement)
    {
        $this->ensureFsAccess($company);
        // Load sections with line items using simple raw queries — avoids any relationship chain issues
        $rawSections = FsSection::where('financial_statement_id', $statement->id)
            ->orderBy('sort_order')
            ->get();

        $sections = $rawSections->map(function ($s) {
            $lineItems = FsLineItem::where('fs_section_id', $s->id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn($li) => [
                    'id'          => $li->id,
                    'label'       => $li->label,
                    'amount'      => (float) $li->amount,
                    'cf_category' => $li->cf_category,
                    'sort_order'  => $li->sort_order,
                ])->values();

            return [
                'id'            => $s->id,
                'statement_type'=> $s->statement_type,
                'section_key'   => $s->section_key,
                'display_name'  => $s->display_name,
                'is_computed'   => (bool) $s->is_computed,
                'computed_from' => $s->computed_from,
                'sort_order'    => $s->sort_order,
                'line_items'    => $lineItems,
            ];
        });

        // IDs of line items that have settlement schedules — plain join, no relationships needed
        $lineItemsWithSchedules = DB::table('fs_settlement_schedules')
            ->join('fs_line_items', 'fs_settlement_schedules.fs_line_item_id', '=', 'fs_line_items.id')
            ->join('fs_sections',   'fs_line_items.fs_section_id', '=', 'fs_sections.id')
            ->where('fs_sections.financial_statement_id', $statement->id)
            ->whereIn('fs_sections.section_key', ['current_assets', 'current_liabilities', 'non_current_liabilities'])
            ->pluck('fs_settlement_schedules.fs_line_item_id')
            ->unique()
            ->values()
            ->toArray();

        return Inertia::render('FinancialStatements/Create', [
            'company'          => ['id' => $company->id, 'name' => $company->name, 'currency' => $company->invested_currency ?? 'USD'],
            'statement'        => [
                'id'          => $statement->id,
                'period_from' => $statement->period_from->format('Y-m-d'),
                'period_to'   => $statement->period_to->format('Y-m-d'),
                'status'      => $statement->status,
                'notes'       => $statement->notes,
            ],
            'existingSections'         => $sections,
            'incomeSections'           => $this->incomeSections(),
            'balanceSections'          => $this->balanceSections(),
            'cashflowSections'         => $this->cashflowSections(),
            'lineItemsWithSchedules'   => $lineItemsWithSchedules,
        ]);
    }

    // ─────────────────────────────────────────────
    // UPDATE — save edits
    // ─────────────────────────────────────────────
    public function update(Request $request, PortfolioCompany $company, FinancialStatement $statement)
    {
        $this->ensureFsAccess($company);
        $request->validate([
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after:period_from',
            'status'      => 'required|in:draft,final',
            'notes'       => 'nullable|string|max:2000',
            'sections'    => 'required|array',
        ]);

        DB::transaction(function () use ($request, $statement) {
            $statement->update([
                'period_from' => $request->period_from,
                'period_to'   => $request->period_to,
                'status'      => $request->status,
                'notes'       => $request->notes,
            ]);

            // ── Preserve settlement schedules before deleting line items ──
            // Use plain DB queries — no relationship chains that could fail
            $savedSchedules = [];
            $scheduleRows = DB::table('fs_settlement_schedules')
                ->join('fs_line_items', 'fs_settlement_schedules.fs_line_item_id', '=', 'fs_line_items.id')
                ->join('fs_sections',   'fs_line_items.fs_section_id', '=', 'fs_sections.id')
                ->where('fs_sections.financial_statement_id', $statement->id)
                ->select(
                    'fs_sections.section_key',
                    'fs_line_items.label as item_label',
                    'fs_settlement_schedules.month',
                    'fs_settlement_schedules.amount',
                    'fs_settlement_schedules.notes'
                )
                ->get();

            foreach ($scheduleRows as $row) {
                $mapKey = $row->section_key . '::' . $row->item_label;
                $savedSchedules[$mapKey][] = [
                    'month'  => $row->month,
                    'amount' => $row->amount,
                    'notes'  => $row->notes,
                ];
            }

            // Delete old sections (cascades to line items and their schedules)
            FsSection::where('financial_statement_id', $statement->id)->delete();

            // Re-save sections and line items fresh
            $this->saveSections($statement, $request->sections);
            $this->calculateAndSaveRatios($statement);

            // ── Re-attach settlement schedules to newly created line items ──
            if (!empty($savedSchedules)) {
                $newSections = FsSection::where('financial_statement_id', $statement->id)
                    ->with('lineItems')
                    ->get();

                foreach ($newSections as $sec) {
                    foreach ($sec->lineItems as $li) {
                        $mapKey = $sec->section_key . '::' . $li->label;
                        if (isset($savedSchedules[$mapKey])) {
                            foreach ($savedSchedules[$mapKey] as $sch) {
                                \App\Models\FsSettlementSchedule::create([
                                    'fs_line_item_id' => $li->id,
                                    'month'           => $sch['month'],
                                    'amount'          => $sch['amount'],
                                    'notes'           => $sch['notes'],
                                ]);
                            }
                        }
                    }
                }
            }
        });

        return redirect()
            ->route('financial-statements.index', $statement->portfolio_company_id)
            ->with('success', 'Financial statement updated successfully.');
    }

    // ─────────────────────────────────────────────
    // VIEW — read-only with ratios + common-size
    // ─────────────────────────────────────────────
    public function show(PortfolioCompany $company, FinancialStatement $statement)
    {
        $this->ensureFsAccess($company);
        $sections = FsSection::where('financial_statement_id', $statement->id)
            ->with('lineItems')
            ->orderBy('statement_type')
            ->orderBy('sort_order')
            ->get();

        // Build totals map for computed rows + common-size
        $totals = $this->buildTotals($sections);

        // Common-size base values
        $revenueBase    = $totals['sales_revenue'] ?? 0;
        $totalAssetBase = $totals['total_assets']  ?? 0;

        $grouped = ['income' => [], 'balance_sheet' => [], 'cashflow' => []];

        foreach ($sections as $sec) {
            $sectionTotal = $totals[$sec->section_key] ?? 0;
            $commonBase   = in_array($sec->statement_type, ['income']) ? $revenueBase : $totalAssetBase;
            $commonPct    = ($commonBase != 0) ? round(($sectionTotal / $commonBase) * 100, 2) : null;

            $grouped[$sec->statement_type][] = [
                'id'            => $sec->id,
                'section_key'   => $sec->section_key,
                'display_name'  => $sec->display_name,
                'is_computed'   => (bool) $sec->is_computed,
                'total'         => $sectionTotal,
                'common_size'   => $commonPct,
                'line_items'    => $sec->lineItems->map(fn($li) => [
                    'label'  => $li->label,
                    'amount' => (float) $li->amount,
                ])->values(),
            ];
        }

        $ratios = FsRatio::where('financial_statement_id', $statement->id)
            ->orderBy('ratio_group')
            ->get()
            ->groupBy('ratio_group')
            ->map(fn($group) => $group->map(fn($r) => [
                'key'   => $r->ratio_key,
                'label' => $r->ratio_label,
                'value' => $r->ratio_value !== null ? (float) $r->ratio_value : null,
            ])->values());

        // Balance sheet validation
        $bsBalance = null;
        if (isset($totals['total_assets']) && isset($totals['total_liabilities_equity'])) {
            $diff = abs($totals['total_assets'] - $totals['total_liabilities_equity']);
            $bsBalance = ['balanced' => $diff < 1, 'difference' => $diff];
        }

        return Inertia::render('FinancialStatements/Show', [
            'company'   => ['id' => $company->id, 'name' => $company->name],
            'statement' => [
                'id'          => $statement->id,
                'period_from' => $statement->period_from,
                'period_to'   => $statement->period_to,
                'currency'    => $statement->currency,
                'status'      => $statement->status,
                'notes'       => $statement->notes,
            ],
            'sections'  => $grouped,
            'ratios'    => $ratios,
            'bsBalance' => $bsBalance,
        ]);
    }

    // ─────────────────────────────────────────────
    // MULTI-PERIOD COMPARISON
    // Supports three modes passed as query param ?mode=:
    //   monthly  (default) — all statements in a chosen year, month columns + Q1-Q4 + YTD
    //   custom   — user-picked statement IDs side-by-side (classic compare)
    //   yoy      — two full years side-by-side with month-vs-month and % change
    // ─────────────────────────────────────────────
    public function compare(Request $request, PortfolioCompany $company)
    {
        $this->ensureFsAccess($company);
        $mode = $request->query('mode', 'monthly');

        // ── Fetch ALL statements for this company once ──
        $allStatements = FinancialStatement::where('portfolio_company_id', $company->id)
            ->orderBy('period_from')
            ->get();

        // ── Build available years list for the dropdowns ──
        $availableYears = $allStatements
            ->map(fn($s) => \Carbon\Carbon::parse($s->period_from)->year)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
      
        // ── Resolve which statements to show as columns ──
        if ($mode === 'monthly' || $mode === 'yoy') {

            // Year A (required for both monthly and yoy)
            $yearA = (int) $request->query('year_a', $availableYears[count($availableYears) - 1] ?? now()->year);
            $yearB = ($mode === 'yoy') ? (int) $request->query('year_b', $yearA - 1) : null;

            $statementsA = $allStatements->filter(
                fn($s) => \Carbon\Carbon::parse($s->period_from)->year === $yearA
            )->sortBy('period_from')->values();

            $statementsB = ($yearB !== null) ? $allStatements->filter(
                fn($s) => \Carbon\Carbon::parse($s->period_from)->year === $yearB
            )->sortBy('period_from')->values() : collect();

            if ($statementsA->isEmpty()) {
                return redirect()->route('financial-statements.compare', $company->id)
                    ->withErrors(['year' => 'No statements found for the selected year.']);
            }

            // Build columns for Year A
            $columnsA = $this->buildColumns($statementsA, $yearA);
            $columnsB = ($statementsB->isNotEmpty()) ? $this->buildColumns($statementsB, $yearB) : [];

            // Build row labels from Year A first statement
            $rows = $this->buildRowLabels($statementsA->first());

            return Inertia::render('FinancialStatements/Compare', [
                'company'        => ['id' => $company->id, 'name' => $company->name],
                'mode'           => $mode,
                'rows'           => $rows,
                'columnsA'       => $columnsA,
                'columnsB'       => $columnsB,
                'yearA'          => $yearA,
                'yearB'          => $yearB,
                'availableYears' => $availableYears,
                // For legacy custom mode compatibility
                'columns'        => $columnsA,
            ]);

        } else {
            // ── Custom mode: user picks specific IDs ──
            $ids = $request->query('ids', []);
            if (empty($ids) || count($ids) < 2) {
                // Fallback to monthly mode with latest year
                return redirect()->route('financial-statements.compare', $company->id)
                    ->with('mode', 'monthly');
            }

            $statements = $allStatements->whereIn('id', $ids)->sortBy('period_from')->values();

            if ($statements->isEmpty()) {
                return back()->withErrors(['ids' => 'No valid statements found.']);
            }

            $columns = $this->buildColumns($statements, null);
            $rows    = $this->buildRowLabels($statements->first());
       
            return Inertia::render('FinancialStatements/Compare', [
                'company'        => ['id' => $company->id, 'name' => $company->name],
                'mode'           => 'custom',
                'rows'           => $rows,
                'columns'        => $columns,
                'columnsA'       => $columns,
                'columnsB'       => [],
                'yearA'          => null,
                'yearB'          => null,
                'availableYears' => $availableYears,
            ]);
            
        }
    }

    /**
     * Build column data for a collection of statements.
     * For income statement sections: non-computed keys are SUMMED across periods (flow items).
     * For balance sheet: last period value is used (stock items).
     * Also appends Q1/Q2/Q3/Q4 and YTD aggregate columns.
     */
    private function buildColumns($statements, ?int $year): array
    {
        $monthColumns = [];

        foreach ($statements as $stmt) {
            $sections = FsSection::where('financial_statement_id', $stmt->id)
                ->with('lineItems')
                ->orderBy('statement_type')
                ->orderBy('sort_order')
                ->get();

            $totals = $this->buildTotals($sections);

            // Build line_items map: section_key => [ label => amount ]
            $lineItems = [];
            foreach ($sections as $sec) {
                if (!$sec->is_computed) {
                    $lineItems[$sec->section_key] = [];
                    foreach ($sec->lineItems->sortBy('sort_order') as $li) {
                        $lineItems[$sec->section_key][$li->label] = (float) $li->amount;
                    }
                }
            }

            $monthColumns[] = [
                'id'          => $stmt->id,
                'period_from' => $stmt->period_from->format('Y-m-d'),
                'period_to'   => $stmt->period_to->format('Y-m-d'),
                'month_num'   => (int) $stmt->period_from->format('n'),
                'label'       => $stmt->period_from->format('M Y'),
                'col_type'    => 'month',
                'totals'      => $totals,
                'line_items'  => $lineItems,
            ];
        }

        if ($year === null || count($monthColumns) < 2) {
            return $monthColumns;
        }

        // ── Build quarterly aggregate columns ──
        $quarters = [
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12],
        ];

        $allKeys = array_keys($monthColumns[0]['totals'] ?? []);

        // Identify balance-sheet keys (stock items — use last value, not sum)
        $balanceKeys = ['current_assets','non_current_assets','total_assets',
                        'current_liabilities','non_current_liabilities','total_liabilities',
                        'equity','total_equity','total_liabilities_equity'];

        $aggregateCols = [];

        foreach ($quarters as $qLabel => $months) {
            $qCols = array_filter($monthColumns, fn($c) => in_array($c['month_num'], $months));
            if (empty($qCols)) continue;

            $qTotals    = $this->aggregateTotals(array_values($qCols), $allKeys, $balanceKeys);
            $qLineItems = $this->aggregateLineItems(array_values($qCols), $balanceKeys);
            $aggregateCols[] = [
                'id'          => null,
                'period_from' => null,
                'period_to'   => null,
                'month_num'   => null,
                'label'       => $qLabel . ' ' . $year,
                'col_type'    => 'quarter',
                'totals'      => $qTotals,
                'line_items'  => $qLineItems,
            ];
        }

        // ── YTD column ──
        $ytdTotals    = $this->aggregateTotals($monthColumns, $allKeys, $balanceKeys);
        $ytdLineItems = $this->aggregateLineItems($monthColumns, $balanceKeys);
        $ytdCol = [
            'id'          => null,
            'period_from' => null,
            'period_to'   => null,
            'month_num'   => null,
            'label'       => 'YTD ' . $year,
            'col_type'    => 'ytd',
            'totals'      => $ytdTotals,
            'line_items'  => $ytdLineItems,
        ];

        // Interleave: months, then Q after every 3 months, then YTD at end
        $result = [];
        $qIndex = 0;
        $qLabels = array_keys($quarters);

        foreach ($monthColumns as $col) {
            $result[] = $col;
            // After month 3, 6, 9, 12 — insert quarterly column if it exists
            if (in_array($col['month_num'], [3, 6, 9, 12])) {
                $qKey = 'Q' . ceil($col['month_num'] / 3);
                foreach ($aggregateCols as $aq) {
                    if (str_starts_with($aq['label'], $qKey)) {
                        $result[] = $aq;
                        break;
                    }
                }
            }
        }

        $result[] = $ytdCol;

        return $result;
    }

    /**
     * Aggregate totals from multiple columns.
     * Flow items (income statement) → SUM
     * Stock items (balance sheet)   → last value
     * Computed keys                 → re-computed from aggregated base values
     */
    private function aggregateTotals(array $cols, array $allKeys, array $balanceKeys): array
    {
        // Flow keys: all section keys that are NOT balance sheet keys and NOT computed
        // We'll sum everything then re-compute computed keys from section definitions
        $computed = [
            'gross_profit'           => [['key'=>'sales_revenue','sign'=>1],['key'=>'cogs','sign'=>-1]],
            'ebitda'                 => [['key'=>'gross_profit','sign'=>1],['key'=>'marketing_expenses','sign'=>-1],['key'=>'ga_expenses','sign'=>-1]],
            'ebit'                   => [['key'=>'ebitda','sign'=>1],['key'=>'depreciation','sign'=>-1]],
            'ebt'                    => [['key'=>'ebit','sign'=>1],['key'=>'finance_income_expense','sign'=>1]],
            'net_profit'             => [['key'=>'ebt','sign'=>1],['key'=>'taxes','sign'=>-1]],
            'total_assets'           => [['key'=>'current_assets','sign'=>1],['key'=>'non_current_assets','sign'=>1]],
            'total_liabilities'      => [['key'=>'current_liabilities','sign'=>1],['key'=>'non_current_liabilities','sign'=>1]],
            'total_equity'           => [['key'=>'equity','sign'=>1]],
            'total_liabilities_equity'=> [['key'=>'total_liabilities','sign'=>1],['key'=>'total_equity','sign'=>1]],
            'net_cash'               => [['key'=>'cfo','sign'=>1],['key'=>'cfi','sign'=>1],['key'=>'cff','sign'=>1]],
        ];

        $result = [];
        $computedKeys = array_keys($computed);

        // First: aggregate non-computed keys
        foreach ($allKeys as $key) {
            if (in_array($key, $computedKeys)) continue;

            if (in_array($key, $balanceKeys)) {
                // Stock item: use last available value
                $lastCol = end($cols);
                $result[$key] = $lastCol['totals'][$key] ?? 0;
            } else {
                // Flow item: sum
                $result[$key] = array_sum(array_column(array_map(fn($c) => ['v' => $c['totals'][$key] ?? 0], $cols), 'v'));
            }
        }

        // Then: compute derived keys
        foreach ($computedKeys as $key) {
            if (!isset($computed[$key])) continue;
            $val = 0;
            foreach ($computed[$key] as $part) {
                $val += ($result[$part['key']] ?? 0) * $part['sign'];
            }
            $result[$key] = $val;
        }

        return $result;
    }

    /**
     * Build row label definitions from a statement's sections.
     */
    /**
     * Aggregate line items across multiple columns.
     * Flow sections (income/cashflow) → SUM amounts by label.
     * Balance sheet sections          → use last column's value.
     */
    private function aggregateLineItems(array $cols, array $balanceKeys): array
    {
        $result = [];
        // Collect all section keys present
        $sectionKeys = [];
        foreach ($cols as $col) {
            foreach (array_keys($col['line_items'] ?? []) as $skey) {
                $sectionKeys[$skey] = true;
            }
        }

        foreach (array_keys($sectionKeys) as $skey) {
            $isBalance = in_array($skey, $balanceKeys);
            // Collect all unique labels across columns for this section
            $labels = [];
            foreach ($cols as $col) {
                foreach (array_keys($col['line_items'][$skey] ?? []) as $lbl) {
                    $labels[$lbl] = true;
                }
            }

            $result[$skey] = [];
            foreach (array_keys($labels) as $label) {
                if ($isBalance) {
                    // Stock: use last available value
                    $val = 0;
                    foreach ($cols as $col) {
                        if (isset($col['line_items'][$skey][$label])) {
                            $val = $col['line_items'][$skey][$label];
                        }
                    }
                    $result[$skey][$label] = $val;
                } else {
                    // Flow: sum
                    $sum = 0;
                    foreach ($cols as $col) {
                        $sum += $col['line_items'][$skey][$label] ?? 0;
                    }
                    $result[$skey][$label] = $sum;
                }
            }
        }

        return $result;
    }

    private function buildRowLabels(FinancialStatement $stmt): \Illuminate\Support\Collection
    {
        return FsSection::where('financial_statement_id', $stmt->id)
            ->with('lineItems')
            ->orderBy('statement_type')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($s) => [
                'key'            => $s->section_key,
                'label'          => $s->display_name,
                'statement_type' => $s->statement_type,
                'is_computed'    => (bool) $s->is_computed,
                // Sub-row labels in entry order — used for collapsible rows in Compare
                'line_item_labels' => $s->is_computed
                    ? []
                    : $s->lineItems->sortBy('sort_order')->pluck('label')->values()->toArray(),
            ]);
    }

    // ─────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────
    public function destroy(PortfolioCompany $company, FinancialStatement $statement)
    {
        $this->ensureFsAccess($company);
        $statement->delete();
        return back()->with('success', 'Financial statement deleted.');
    }


// ═══════════════════════════════════════════════════════════════════════════
// FILE 1 — Add this method inside FinancialStatementController.php
//           Place it anywhere among the other public methods, e.g. after destroy()
// ═══════════════════════════════════════════════════════════════════════════

    // ─────────────────────────────────────────────
    // COPY — duplicate a statement's structure (and optionally amounts)
    // POST /portfolio-companies/{company}/financial-statements/{statement}/copy
    // ─────────────────────────────────────────────
    public function copy(Request $request, PortfolioCompany $company, FinancialStatement $statement)
    {
        $this->ensureFsAccess($company);
        $request->validate([
            'period_from'  => 'required|date',
            'period_to'    => 'required|date|after:period_from',
            'copy_amounts' => 'nullable|boolean',
        ]);

        $copyAmounts = (bool) $request->input('copy_amounts', true);

        DB::transaction(function () use ($request, $company, $statement, $copyAmounts) {

            // 1. Create the new statement header
            $newStatement = FinancialStatement::create([
                'portfolio_company_id' => $company->id,
                'period_from'          => $request->period_from,
                'period_to'            => $request->period_to,
                'currency'             => $statement->currency,
                'status'               => 'draft',          // Always draft
                'notes'                => $statement->notes,
                'created_by'           => auth()->id(),
            ]);

            // 2. Load the source statement's sections + line items
            $sourceSections = FsSection::where('financial_statement_id', $statement->id)
                ->orderBy('sort_order')
                ->get();

            foreach ($sourceSections as $sourceSection) {
                // 3. Duplicate the section
                $newSection = FsSection::create([
                    'financial_statement_id' => $newStatement->id,
                    'statement_type'         => $sourceSection->statement_type,
                    'section_key'            => $sourceSection->section_key,
                    'display_name'           => $sourceSection->display_name,
                    'is_computed'            => $sourceSection->is_computed,
                    'computed_from'          => $sourceSection->computed_from,
                    'sort_order'             => $sourceSection->sort_order,
                ]);

                // 4. Duplicate line items (only for non-computed sections)
                if (!$sourceSection->is_computed) {
                    $sourceLineItems = FsLineItem::where('fs_section_id', $sourceSection->id)
                        ->orderBy('sort_order')
                        ->get();

                    foreach ($sourceLineItems as $li) {
                        FsLineItem::create([
                            'fs_section_id' => $newSection->id,
                            'label'         => $li->label,
                            'amount'        => $copyAmounts ? (float) $li->amount : 0.0,
                            'cf_category'   => $li->cf_category,
                            'sort_order'    => $li->sort_order,
                        ]);
                    }
                }
            }

            // 5. Recalculate ratios for the new statement
            $this->calculateAndSaveRatios($newStatement);
        });

        return redirect()
            ->route('financial-statements.index', $company->id)
            ->with('success', 'Statement copied successfully. It has been saved as a Draft — edit it to update the dates and figures.');
    }




    // ─────────────────────────────────────────────
    // EXPORT TO EXCEL
    // ─────────────────────────────────────────────
    public function export(PortfolioCompany $company, FinancialStatement $statement)
    {
        $this->ensureFsAccess($company);
        abort_unless((int) $statement->portfolio_company_id === (int) $company->id, 404);
        try {
            $filename = $company->name . '_FinancialStatement_' . $statement->period_from . '_' . $statement->period_to . '.xlsx';
            return Excel::download(new FinancialStatementExport($statement), $filename);
        } catch (\Throwable $e) {
            \Log::error('Financial statement export failed: ' . $e->getMessage(), ['exception' => $e]);
            return back()->withErrors(['export' => 'Export failed. Please try again or contact support.']);
        }
    }

    // ─────────────────────────────────────────────
    // UPLOAD PAGE — show the upload form
    // ─────────────────────────────────────────────
    public function uploadPage(PortfolioCompany $company)
    {
        $this->ensureFsAccess($company);
        $statements = FinancialStatement::where('portfolio_company_id', $company->id)
            ->orderByDesc('period_to')
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'period_from' => $s->period_from->format('Y-m-d'),
                'period_to'   => $s->period_to->format('Y-m-d'),
                'label'       => $s->period_from->format('M Y') . ' — ' . $s->period_to->format('M Y'),
            ]);

        return Inertia::render('FinancialStatements/Upload', [
            'company'    => ['id' => $company->id, 'name' => $company->name, 'currency' => $company->invested_currency ?? 'USD'],
            'statements' => $statements,   // for template picker
            'hasData'    => $statements->isNotEmpty(),
        ]);
    }

    // ─────────────────────────────────────────────
    // DOWNLOAD TEMPLATE — generate Excel from chosen statement structure
    // ─────────────────────────────────────────────
    public function downloadTemplate(Request $request, PortfolioCompany $company)
    {
        $this->ensureFsAccess($company);
        $request->validate([
            'statement_id' => 'required|exists:financial_statements,id',
        ]);

        $statement = FinancialStatement::findOrFail($request->statement_id);
        abort_unless((int) $statement->portfolio_company_id === (int) $company->id, 404);

        $sections = FsSection::where('financial_statement_id', $statement->id)
            ->with('lineItems')
            ->orderBy('statement_type')
            ->orderBy('sort_order')
            ->get();

        $filename = $company->name . '_FS_Template_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new \App\Exports\FinancialStatementTemplateExport($sections, $company),
            $filename
        );
    }

    // ─────────────────────────────────────────────
    // PROCESS UPLOAD — validate & import Excel
    // ─────────────────────────────────────────────
    public function processUpload(Request $request, PortfolioCompany $company)
    {
        $this->ensureFsAccess($company);
        $request->validate([
            'statement_id' => 'required|exists:financial_statements,id',
            'period_from'  => 'required|date',
            'period_to'    => 'required|date|after:period_from',
            'status'       => 'required|in:draft,final',
            'notes'        => 'nullable|string|max:2000',
            'file'         => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        // Load the template statement structure
        $templateStatement = FinancialStatement::findOrFail($request->statement_id);
        $templateSections  = FsSection::where('financial_statement_id', $templateStatement->id)
            ->with('lineItems')
            ->orderBy('statement_type')
            ->orderBy('sort_order')
            ->get();

        // Parse the uploaded Excel
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());

        // We expect 3 sheets: Income Statement, Balance Sheet, Cash Flow
        $sheetMap = [
            'Income Statement' => 'income',
            'Balance Sheet'    => 'balance_sheet',
            'Cash Flow'        => 'cashflow',
        ];

        $uploadedData  = [];  // [section_key => [label => amount]]
        $missingSheets = [];
        $mismatchedLabels = [];

        foreach ($sheetMap as $sheetName => $type) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (!$sheet) {
                $missingSheets[] = $sheetName;
                continue;
            }

            $rows = $sheet->toArray();

            // Build a flat map of label => amount from the uploaded sheet
            // Skip header rows (rows where column B is not numeric)
            $uploadedLabels = [];
            foreach ($rows as $row) {
                $label  = trim((string) ($row[0] ?? ''));
                $amount = $row[1] ?? null;

                if ($label === '' || $label === 'Description' || is_null($amount)) continue;
                // Skip section headers (they have empty amount or non-numeric)
                if (!is_numeric($amount)) continue;

                $uploadedLabels[$label] = (float) $amount;
            }

            $uploadedData[$type] = $uploadedLabels;
        }

        if (!empty($missingSheets)) {
            return back()->withErrors([
                'file' => 'Excel file is missing required sheets: ' . implode(', ', $missingSheets) .
                          '. Please download a fresh template and try again.',
            ]);
        }

        // Validate: every line item label in the template must exist in the upload
        foreach ($templateSections as $sec) {
            if ($sec->is_computed) continue;

            $type           = $sec->statement_type;
            $uploadedLabels = array_keys($uploadedData[$type] ?? []);

            foreach ($sec->lineItems as $li) {
                if (!in_array(trim($li->label), $uploadedLabels)) {
                    $mismatchedLabels[] = [
                        'sheet'   => array_search($type, $sheetMap),
                        'missing' => $li->label,
                    ];
                }
            }
        }

        if (!empty($mismatchedLabels)) {
            $details = collect($mismatchedLabels)
                ->map(fn($m) => '• [' . $m['sheet'] . '] "' . $m['missing'] . '"')
                ->implode("\n");

            return back()->withErrors([
                'file' => "The following line items from the template were not found in your uploaded file:\n\n" . $details .
                          "\n\nPlease fix the labels in your Excel file and upload again.",
            ]);
        }

        // All validated — save the new statement
        DB::transaction(function () use ($request, $company, $templateSections, $uploadedData, $sheetMap) {
            $statement = FinancialStatement::create([
                'portfolio_company_id' => $company->id,
                'period_from'          => $request->period_from,
                'period_to'            => $request->period_to,
                'currency'             => $company->invested_currency ?? 'USD',
                'status'               => $request->status,
                'notes'                => $request->notes,
                'created_by'           => auth()->id(),
            ]);

            foreach ($templateSections as $sec) {
                $newSection = FsSection::create([
                    'financial_statement_id' => $statement->id,
                    'statement_type'         => $sec->statement_type,
                    'section_key'            => $sec->section_key,
                    'display_name'           => $sec->display_name,
                    'is_computed'            => $sec->is_computed,
                    'computed_from'          => $sec->computed_from,
                    'sort_order'             => $sec->sort_order,
                ]);

                if (!$sec->is_computed) {
                    $type = $sec->statement_type;
                    foreach ($sec->lineItems as $idx => $li) {
                        $amount = $uploadedData[$type][trim($li->label)] ?? 0;
                        FsLineItem::create([
                            'fs_section_id' => $newSection->id,
                            'label'         => $li->label,
                            'amount'        => $amount,
                            'cf_category'   => $li->cf_category,
                            'sort_order'    => $idx,
                        ]);
                    }
                }
            }

            $this->calculateAndSaveRatios($statement);
        });

        return redirect()
            ->route('financial-statements.index', $company->id)
            ->with('success', 'Financial statement imported successfully from Excel.');
    }

    // ─────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────

    /**
     * Save sections + line items from the form payload.
     * $sections is an array indexed by section_key, each with a 'line_items' array.
     */
    private function saveSections(FinancialStatement $statement, array $sectionsPayload): void
    {
        $allTemplates = array_merge(
            array_map(fn($s) => array_merge($s, ['type' => 'income']),      $this->incomeSections()),
            array_map(fn($s) => array_merge($s, ['type' => 'balance_sheet']),$this->balanceSections()),
            array_map(fn($s) => array_merge($s, ['type' => 'cashflow']),     $this->cashflowSections()),
        );


        foreach ($allTemplates as $template) {
            $key = $template['key'];
           

            $section = FsSection::create([
                'financial_statement_id' => $statement->id,
                'statement_type'         => $template['type'],
                'section_key'            => $key,
                'display_name'           => $template['label'],
                'is_computed'            => $template['computed'],
                'computed_from'          => $template['from'] ? json_encode($template['from']) : null,
                'sort_order'             => $template['sort'],
            ]);

            // Save line items for non-computed sections
            if (!$template['computed'] && isset($sectionsPayload[$key]['line_items'])) {
                foreach ($sectionsPayload[$key]['line_items'] as $idx => $item) {
                    if (isset($item['label']) && $item['label'] !== '') {
                        FsLineItem::create([
                            'fs_section_id' => $section->id,
                            'label'         => $item['label'],
                            'amount'        => (float) ($item['amount'] ?? 0),
                            'cf_category'   => $item['cf_category'] ?? null,
                            'sort_order'    => $idx,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * DSO = (Accounts Receivable / Revenue) × 365
     * Looks for line items in current_assets whose label contains "receivable"
     */
    private function calcDso($sections, array $t): ?float
    {
        $revenue = $t['sales_revenue'] ?? 0;
        if ($revenue == 0) return null;

        $receivables = 0;
        foreach ($sections as $sec) {
            if ($sec->section_key === 'current_assets') {
                foreach ($sec->lineItems as $li) {
                    if (stripos($li->label, 'receivable') !== false) {
                        $receivables += (float) $li->amount;
                    }
                }
            }
        }
        if ($receivables == 0) return null;
        return round(($receivables / $revenue) * 365, 1);
    }

    /**
     * DIO = (Inventory / COGS) × 365
     * Looks for line items in current_assets whose label contains "inventor"
     */
    private function calcDio($sections, array $t): ?float
    {
        $cogs = $t['cogs'] ?? 0;
        if ($cogs == 0) return null;

        $inventory = 0;
        foreach ($sections as $sec) {
            if ($sec->section_key === 'current_assets') {
                foreach ($sec->lineItems as $li) {
                    if (stripos($li->label, 'inventory') !== false) {
                        $inventory += (float) $li->amount;
                    }
                }
            }
        }
        if ($inventory == 0) return null;
        return round(($inventory / $cogs) * 365, 1);
    }

    /**
     * DPO = (Accounts Payable / COGS) × 365
     * Looks for line items in current_liabilities whose label contains "payable"
     */
    private function calcDpo($sections, array $t): ?float
    {
        $cogs = $t['cogs'] ?? 0;
        if ($cogs == 0) return null;

        $payables = 0;
        foreach ($sections as $sec) {
            if ($sec->section_key === 'current_liabilities') {
                foreach ($sec->lineItems as $li) {
                    if (stripos($li->label, 'payable') !== false) {
                        $payables += (float) $li->amount;
                    }
                }
            }
        }
        if ($payables == 0) return null;
        return round(($payables / $cogs) * 365, 1);
    }


    /**
     * Receivables Turnover = Sales Revenue / Receivables
     * Uses same label detection as DSO.
     */
    private function calcReceivablesTurnover($sections, array $t): ?float
    {
        $revenue = $t['sales_revenue'] ?? 0;
        if ($revenue == 0) return null;

        $receivables = 0;
        foreach ($sections as $sec) {
            if ($sec->section_key === 'current_assets') {
                foreach ($sec->lineItems as $li) {
                    if (stripos($li->label, 'receivable') !== false) {
                        $receivables += (float) $li->amount;
                    }
                }
            }
        }
        if ($receivables == 0) return null;
        return round($revenue / $receivables, 2);
    }
////////////////////////////////////////////////////////////////////////////////////
    /**
     * Inventory Turnover = COGS / Inventory
     * Uses same label detection as DIO.
     */
    private function calcInventoryTurnover($sections, array $t): ?float
    {
        $cogs = $t['cogs'] ?? 0;
        if ($cogs == 0) return null;

        $inventory = 0;
        foreach ($sections as $sec) {
            if ($sec->section_key === 'current_assets') {
                foreach ($sec->lineItems as $li) {
                    if (stripos($li->label, 'inventory') !== false) {
                        $inventory += (float) $li->amount;
                    }
                }
            }
        }
        if ($inventory == 0) return null;
        return round($cogs / $inventory, 2);
    }


/////////////////////////////////////////////////////////////////////////////

        /**
     * Build a [section_key => total] map.
     * Non-computed = sum of line items.
     * Computed    = formula evaluation in multiple safe passes.
     * Works regardless of section order or old JSON data.
     */
    private function buildTotals($sections): array
    {
        $totals = [];
        // 1. Load all non-computed totals first
        foreach ($sections as $sec) {
            if (!$sec->is_computed) {
                $totals[$sec->section_key] = $sec->lineItems->sum('amount');
                }
        }

        // 2. Compute all computed rows in fixed passes (guaranteed propagation)
        for ($pass = 0; $pass < 10; $pass++) {
            foreach ($sections as $sec) {
                if (!$sec->is_computed || !$sec->computed_from) {
                    continue;
                }

                // Handle both: JSON string (DB) or already-decoded array (model cast)
                $formula = $sec->computed_from;
                if (is_string($formula)) {
                    $formula = json_decode($formula, true) ?? [];
                }

                if (!is_array($formula) || empty($formula)) {
                    continue;
                }

                $result = 0.0;
                $canCompute = true;

                foreach ($formula as $part) {
                    $key  = $part['key']  ?? null;
                    $sign = $part['sign'] ?? 1;

                    if ($key === null || !array_key_exists($key, $totals)) {
                        $canCompute = false;
                        break;
                    }

                    $result += $totals[$key] * $sign;
                }

                if ($canCompute) {
                    $totals[$sec->section_key] = $result;
                }
            }
        }

        return $totals;
    }




    private function calculateAndSaveRatios(FinancialStatement $statement): void
    {
        $sections = FsSection::where('financial_statement_id', $statement->id)
            ->with('lineItems')
            ->orderBy('statement_type')   // ← added for consistency
            ->orderBy('sort_order')
            ->get();
        $t = $this->buildTotals($sections);

        $div = fn($n, $d) => ($d != 0) ? round($n / $d, 4) : null;
        $pct = fn($n, $d) => ($d != 0) ? round(($n / $d) * 100, 2) : null;

        $ratios = [
            // ── Profitability ──
            ['group' => 'profitability', 'key' => 'gross_margin_pct',   'label' => 'Gross Margin %',        'value' => $pct($t['gross_profit'] ?? 0,   $t['sales_revenue'] ?? 0)],
            ['group' => 'profitability', 'key' => 'ebitda_margin_pct',  'label' => 'EBITDA Margin %',       'value' => $pct($t['ebitda'] ?? 0,         $t['sales_revenue'] ?? 0)],
            ['group' => 'profitability', 'key' => 'net_margin_pct',     'label' => 'Net Profit Margin %',   'value' => $pct($t['net_profit'] ?? 0,     $t['sales_revenue'] ?? 0)],
            ['group' => 'profitability', 'key' => 'roa',                'label' => 'Return on Assets (ROA)','value' => $pct($t['net_profit'] ?? 0,     $t['total_assets'] ?? 0)],
            ['group' => 'profitability', 'key' => 'roe',                'label' => 'Return on Equity (ROE)','value' => $pct($t['net_profit'] ?? 0,     $t['total_equity'] ?? 0)],

            // ── Liquidity ──
            ['group' => 'liquidity',     'key' => 'current_ratio',      'label' => 'Current Ratio',         'value' => $div($t['current_assets'] ?? 0,     $t['current_liabilities'] ?? 0)],
            ['group' => 'liquidity',     'key' => 'quick_ratio',        'label' => 'Quick Ratio',           'value' => $div(($t['current_assets'] ?? 0) * 0.7, $t['current_liabilities'] ?? 0)],

            // ── Leverage ──
            ['group' => 'leverage',      'key' => 'debt_to_equity',     'label' => 'Debt to Equity',        'value' => $div($t['total_liabilities'] ?? 0,  $t['total_equity'] ?? 0)],
            ['group' => 'leverage',      'key' => 'debt_to_assets',     'label' => 'Debt to Assets',        'value' => $div($t['total_liabilities'] ?? 0,  $t['total_assets'] ?? 0)],
            ['group' => 'leverage',      'key' => 'interest_coverage',  'label' => 'Interest Coverage',     'value' => $div($t['ebit'] ?? 0,               abs($t['finance_income_expense'] ?? 0))],

            // ── Activity ──
            ['group' => 'activity',      'key' => 'asset_turnover',     'label' => 'Asset Turnover',              'value' => $div($t['sales_revenue'] ?? 0,  $t['total_assets'] ?? 0)],
            ['group' => 'activity',      'key' => 'receivables_turnover','label' => 'Receivables Turnover',       'value' => $this->calcReceivablesTurnover($sections, $t)],  // ← fixed
            ['group' => 'activity',      'key' => 'inventory_turnover', 'label' => 'Inventory Turnover',          'value' => $this->calcInventoryTurnover($sections, $t)],   // ← fixed
            ['group' => 'activity',      'key' => 'dso',                'label' => 'Days Sales Outstanding (DSO)','value' => $this->calcDso($sections, $t)],
            ['group' => 'activity',      'key' => 'dio',                'label' => 'Days Inventory Outstanding (DIO)','value' => $this->calcDio($sections, $t)],
            ['group' => 'activity',      'key' => 'dpo',                'label' => 'Days Payable Outstanding (DPO)','value' => $this->calcDpo($sections, $t)],
        ];
        

        FsRatio::where('financial_statement_id', $statement->id)->delete();

        foreach ($ratios as $r) {
            FsRatio::create([
                'financial_statement_id' => $statement->id,
                'ratio_group'            => $r['group'],
                'ratio_key'              => $r['key'],
                'ratio_label'            => $r['label'],
                'ratio_value'            => $r['value'],
            ]);
        }

        // ================================================
        // AUTO-SYNC TO KPI DASHBOARD
        // ================================================

        $financialData = $t;   // all totals (sales_revenue, ebitda, etc.)

        $ratiosData = FsRatio::where('financial_statement_id', $statement->id)
            ->pluck('ratio_value', 'ratio_key')
            ->toArray();

        $allDataForKpis = array_merge($financialData, $ratiosData);

        $periodType  = $this->guessPeriodType($statement->period_from, $statement->period_to);
        $periodLabel = $this->guessPeriodLabel($statement->period_from, $statement->period_to);

        \App\Http\Controllers\KpiController::syncFromStatement(
            $statement->portfolio_company_id,
            $statement->company?->organization_id ?? auth()->user()?->organization_id,
            $periodType,
            $periodLabel,
            $allDataForKpis
        );

    }

    // ─────────────────────────────────────────────
    // PRIOR BALANCE — for cash-flow auto-calculation
    // ─────────────────────────────────────────────
    public function priorBalance(PortfolioCompany $company, Request $request)
    {
        $this->ensureFsAccess($company);
        $request->validate(['before' => 'required|date']);

        $excludeId = $request->integer('exclude_id') ?: null;

        return response()->json([
            'items' => $this->getPriorBalanceItems($company, $request->before, $excludeId),
        ]);
    }

    private function getPriorBalanceItems(PortfolioCompany $company, string $beforeDate, ?int $excludeStatementId = null): array
    {
        $query = FinancialStatement::where('portfolio_company_id', $company->id)
            ->where('period_to', '<', $beforeDate);

        if ($excludeStatementId) {
            $query->where('id', '!=', $excludeStatementId);
        }

        $prior = $query->orderByDesc('period_to')->first();
        if (!$prior) {
            return [];
        }

        $items = [];
        $sections = FsSection::where('financial_statement_id', $prior->id)
            ->where('statement_type', 'balance_sheet')
            ->with('lineItems')
            ->get();

        foreach ($sections as $sec) {
            foreach ($sec->lineItems as $li) {
                $items[$sec->section_key . '::' . $li->label] = (float) $li->amount;
            }
        }

        return $items;
    }

    private function guessPeriodType($from, $to)
        {
            $days = $from->diffInDays($to) + 1;

            if ($days <= 35)   return 'monthly';
            if ($days <= 95)   return 'quarterly';
            return 'annual';
        }

    private function guessPeriodLabel($from, $to)
        {
            $days = $from->diffInDays($to) + 1;

            if ($days <= 35) {
                // monthly → 2025-02
                return $from->format('Y-m');
            }

            if ($days <= 95) {
                // quarterly → 2025-Q1
                $quarter = ceil($from->month / 3);
                return $from->format('Y') . '-Q' . $quarter;
            }

            // annual → 2025
            return $from->format('Y');
        }
       
   }

 