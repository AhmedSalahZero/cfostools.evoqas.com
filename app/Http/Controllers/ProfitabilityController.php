<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PortfolioCompany;
use App\Models\ExpenseData;
use App\Models\SalesData;
use App\Models\ProfitabilityPlMapping;
use App\Models\ProfitabilityManualInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfitabilityController extends Controller
{
    private function authorizeProfitability(PortfolioCompany $company): PortfolioCompany
    {
        return $this->authorizeCompany($company, 'profitability');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // P&L LINE DEFINITIONS
    // ─────────────────────────────────────────────────────────────────────────

    const PL_LINES = [
        'cogs'     => 'Cost of Goods Sold (COGS)',
        'opex'     => 'Operating Expenses (OpEx)',
        'da'       => 'Depreciation & Amortisation (D&A)',
        'interest' => 'Interest Expense',
        'tax'      => 'Tax',
        'other'    => 'Other Expenses',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // MAPPING PAGE — one-time setup
    // ─────────────────────────────────────────────────────────────────────────

    public function mappingPage(PortfolioCompany $company)
    {
        // Get all distinct expense categories for this company
        $categories = ExpenseData::where('portfolio_company_id', $company->id)
            ->whereNotNull('expense_category')
            ->distinct()->pluck('expense_category')->sort()->values();

        // Get existing mappings
        $mappings = ProfitabilityPlMapping::where('portfolio_company_id', $company->id)
            ->pluck('pl_line', 'expense_category');

        return Inertia::render('Profitability/Mapping', [
            'company'    => ['id' => $company->id, 'name' => $company->name],
            'categories' => $categories,
            'mappings'   => $mappings,
            'plLines'    => self::PL_LINES,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE MAPPINGS (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function saveMappings(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'mappings'   => ['required', 'array'],
            'mappings.*' => ['required', 'in:cogs,opex,da,interest,tax,other'],
        ]);

        foreach ($request->mappings as $category => $plLine) {
            ProfitabilityPlMapping::updateOrCreate(
                ['portfolio_company_id' => $company->id, 'expense_category' => $category],
                ['pl_line' => $plLine]
            );
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD PAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboardPage(PortfolioCompany $company)
    {
        $hasSales   = SalesData::where('portfolio_company_id', $company->id)->exists();
        $hasExpense = ExpenseData::where('portfolio_company_id', $company->id)->exists();

        if (!$hasSales && !$hasExpense) {
            return redirect()->route('sales.dashboard', $company->id)
                ->with('flash', ['error' => 'Upload sales and expense data first.']);
        }

        $maxSales   = SalesData::where('portfolio_company_id', $company->id)->max('date');
        $maxExpense = ExpenseData::where('portfolio_company_id', $company->id)->max('date');
        $minSales   = SalesData::where('portfolio_company_id', $company->id)->min('date');
        $minExpense = ExpenseData::where('portfolio_company_id', $company->id)->min('date');

        // Widest range across both datasets for the date picker bounds
        $minDate = min($minSales ?? '2000-01-01', $minExpense ?? '2000-01-01');
        $maxDate = max($maxSales ?? '2000-01-01', $maxExpense ?? '2000-01-01');

        // Default to expense data year so P&L shows meaningful data on first load
        // (expense is the most constrained dataset — if it exists use its year)
        if ($maxExpense) {
            $defaultFrom = date('Y', strtotime($maxExpense)) . '-01-01';
            $defaultTo   = $maxExpense;
        } else {
            $defaultFrom = date('Y', strtotime($maxSales)) . '-01-01';
            $defaultTo   = $maxSales;
        }

        $hasMappings = ProfitabilityPlMapping::where('portfolio_company_id', $company->id)->exists();

        return Inertia::render('Profitability/Dashboard', [
            'company'     => ['id' => $company->id, 'name' => $company->name],
            'defaultFrom' => $defaultFrom,
            'defaultTo'   => $defaultTo,
            'minDate'     => $minDate,
            'maxDate'     => $maxDate,
            'hasMappings' => $hasMappings,
            'plLines'     => self::PL_LINES,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD DATA (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboardData(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'date_from'   => ['required', 'date'],
            'date_to'     => ['required', 'date'],
            'period_type' => ['required', 'in:month,quarter,semi,year'],
        ]);

        $from       = $request->date_from;
        $to         = $request->date_to;
        $cid        = $company->id;
        $periodType = $request->period_type;

        // ── 1. Revenue ──
        $revenue = (float) SalesData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])->sum('net_sales_value');

        // ── 2. Load mappings ──
        $mappings = ProfitabilityPlMapping::where('portfolio_company_id', $cid)
            ->pluck('pl_line', 'expense_category');

        // ── 3. Sum expenses by P&L line ──
        $expenseRows = ExpenseData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_category')
            ->selectRaw('expense_category, SUM(expense_amount) as total')
            ->groupBy('expense_category')->get();

        $byLine = ['cogs' => 0, 'opex' => 0, 'da' => 0, 'interest' => 0, 'tax' => 0, 'other' => 0, 'unmapped' => 0];
        foreach ($expenseRows as $row) {
            $line = $mappings[$row->expense_category] ?? 'unmapped';
            $byLine[$line] = ($byLine[$line] ?? 0) + (float) $row->total;
        }

        // ── 4. Manual inputs for this date range ──
        // We aggregate all manual inputs whose periods fall within the date range
        $manuals = $this->aggregateManualInputs($cid, $from, $to);

        // D&A / Interest / Tax: use mapped expense data + manual inputs
        $da       = $byLine['da']       + $manuals['da'];
        $interest = $byLine['interest'] + $manuals['interest'];
        $tax      = $byLine['tax']      + $manuals['tax'];

        // ── 5. P&L Waterfall ──
        $grossProfit = $revenue - $byLine['cogs'];
        $ebitda      = $grossProfit - $byLine['opex'] - $byLine['other'];
        $ebit        = $ebitda - $da;
        $ebt         = $ebit - $interest;
        $netProfit   = $ebt - $tax;

        $pct = fn($val) => $revenue > 0 ? round($val / $revenue * 100, 2) : 0;

        $waterfall = [
            ['label' => 'Revenue',            'value' => $revenue,      'margin' => 100,            'type' => 'revenue'],
            ['label' => 'COGS',               'value' => $byLine['cogs'],'margin' => $pct($byLine['cogs']), 'type' => 'deduct'],
            ['label' => 'Gross Profit',       'value' => $grossProfit,  'margin' => $pct($grossProfit),     'type' => 'subtotal'],
            ['label' => 'OpEx',               'value' => $byLine['opex'],'margin' => $pct($byLine['opex']), 'type' => 'deduct'],
            ['label' => 'Other Expenses',     'value' => $byLine['other'],'margin' => $pct($byLine['other']),'type' => 'deduct'],
            ['label' => 'EBITDA',             'value' => $ebitda,       'margin' => $pct($ebitda),          'type' => 'subtotal'],
            ['label' => 'D&A',                'value' => $da,           'margin' => $pct($da),              'type' => 'deduct'],
            ['label' => 'EBIT',               'value' => $ebit,         'margin' => $pct($ebit),            'type' => 'subtotal'],
            ['label' => 'Interest',           'value' => $interest,     'margin' => $pct($interest),        'type' => 'deduct'],
            ['label' => 'EBT',                'value' => $ebt,          'margin' => $pct($ebt),             'type' => 'subtotal'],
            ['label' => 'Tax',                'value' => $tax,          'margin' => $pct($tax),             'type' => 'deduct'],
            ['label' => 'Net Profit',         'value' => $netProfit,    'margin' => $pct($netProfit),       'type' => 'net'],
        ];

        // ── 6. KPI cards (5 key metrics) ──
        $kpis = [
            'revenue'      => ['amount' => $revenue,     'margin' => 100],
            'gross_profit' => ['amount' => $grossProfit, 'margin' => $pct($grossProfit)],
            'ebitda'       => ['amount' => $ebitda,      'margin' => $pct($ebitda)],
            'ebit'         => ['amount' => $ebit,        'margin' => $pct($ebit)],
            'ebt'          => ['amount' => $ebt,         'margin' => $pct($ebt)],
            'net_profit'   => ['amount' => $netProfit,   'margin' => $pct($netProfit)],
            'unmapped'     => $byLine['unmapped'],
        ];

        // ── 7. Trend data (all 5 metrics, period-grouped) ──
        $trend = $this->buildTrend($cid, $from, $to, $periodType, $mappings);

        // ── 8. Manual inputs list for the date range (for the manual input UI) ──
        $manualList = $this->getManualInputsList($cid, $from, $to, $periodType);

        return response()->json([
            'kpis'        => $kpis,
            'waterfall'   => $waterfall,
            'trend'       => $trend,
            'manual_list' => $manualList,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE / UPDATE MANUAL INPUT (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function saveManualInput(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'period_type'     => ['required', 'in:month,quarter,semi,year'],
            'period_label'    => ['required', 'string', 'max:20'],
            'da_amount'       => ['nullable', 'numeric', 'min:0'],
            'interest_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount'      => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        ProfitabilityManualInput::updateOrCreate(
            [
                'portfolio_company_id' => $company->id,
                'period_type'          => $request->period_type,
                'period_label'         => $request->period_label,
            ],
            [
                'da_amount'       => $request->da_amount       ?? 0,
                'interest_amount' => $request->interest_amount ?? 0,
                'tax_amount'      => $request->tax_amount      ?? 0,
                'notes'           => $request->notes,
                'created_by'      => Auth::id(),
            ]
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Build Trend Data
    // Returns periods array + rows for each of the 5 metrics
    // ─────────────────────────────────────────────────────────────────────────

    private function buildTrend($cid, $from, $to, $periodType, $mappings): array
    {
        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($periodType);

        // Revenue by period
        $revenueRows = SalesData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("$labelExpr as period, $sortExpr as sort_key, SUM(net_sales_value) as total")
            ->groupBy('period', 'sort_key')->orderBy('sort_key')->get()
            ->keyBy('period');

        // Expense by period + category
        $expenseRows = ExpenseData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_category')
            ->selectRaw("expense_category, $labelExpr as period, $sortExpr as sort_key, SUM(expense_amount) as total")
            ->groupBy('expense_category', 'period', 'sort_key')->orderBy('sort_key')->get();

        // Collect all periods in order
        $periods = $revenueRows->keys()
            ->merge($expenseRows->pluck('period')->unique())
            ->unique()->values();

        // Sort periods by sort_key
        $sortKeys = collect();
        foreach ($revenueRows as $p => $r) $sortKeys[$p] = $r->sort_key;
        foreach ($expenseRows as $r) { if (!isset($sortKeys[$r->period])) $sortKeys[$r->period] = $r->sort_key; }
        $periods = $periods->sortBy(fn($p) => $sortKeys[$p] ?? 0)->values();

        // Build expense by period by pl_line
        $expByPeriod = [];
        foreach ($expenseRows as $r) {
            $line = $mappings[$r->expense_category] ?? 'other';
            $expByPeriod[$r->period][$line] = ($expByPeriod[$r->period][$line] ?? 0) + (float) $r->total;
        }

        // Build metric rows per period
        $metrics = ['gross_profit' => [], 'ebitda' => [], 'ebit' => [], 'ebt' => [], 'net_profit' => []];

        foreach ($periods as $p) {
            $rev      = (float) ($revenueRows[$p]->total ?? 0);
            $cogs     = $expByPeriod[$p]['cogs']     ?? 0;
            $opex     = $expByPeriod[$p]['opex']     ?? 0;
            $daExp    = $expByPeriod[$p]['da']        ?? 0;
            $intExp   = $expByPeriod[$p]['interest']  ?? 0;
            $taxExp   = $expByPeriod[$p]['tax']       ?? 0;
            $other    = $expByPeriod[$p]['other']     ?? 0;

            // Manual inputs for this period
            $manual   = ProfitabilityManualInput::where('portfolio_company_id', $cid)
                ->where('period_type', $periodType)->where('period_label', $p)->first();
            $da       = $daExp  + (float) ($manual->da_amount       ?? 0);
            $interest = $intExp + (float) ($manual->interest_amount ?? 0);
            $tax      = $taxExp + (float) ($manual->tax_amount      ?? 0);

            $gp  = $rev - $cogs;
            $ebd = $gp - $opex - $other;
            $ebi = $ebd - $da;
            $ebt = $ebi - $interest;
            $net = $ebt - $tax;

            $metrics['gross_profit'][$p] = ['value' => round($gp,  2), 'margin' => $rev > 0 ? round($gp/$rev*100,1)  : 0];
            $metrics['ebitda'][$p]       = ['value' => round($ebd, 2), 'margin' => $rev > 0 ? round($ebd/$rev*100,1) : 0];
            $metrics['ebit'][$p]         = ['value' => round($ebi, 2), 'margin' => $rev > 0 ? round($ebi/$rev*100,1) : 0];
            $metrics['ebt'][$p]          = ['value' => round($ebt, 2), 'margin' => $rev > 0 ? round($ebt/$rev*100,1) : 0];
            $metrics['net_profit'][$p]   = ['value' => round($net, 2), 'margin' => $rev > 0 ? round($net/$rev*100,1) : 0];
        }

        // Add GR% (growth rate vs previous period) to each metric
        $periodsArr = $periods->toArray();
        foreach ($metrics as $key => &$metricData) {
            foreach ($periodsArr as $i => $p) {
                if ($i === 0) { $metricData[$p]['gr'] = null; continue; }
                $prev = $metricData[$periodsArr[$i-1]]['value'] ?? 0;
                $curr = $metricData[$p]['value'] ?? 0;
                $metricData[$p]['gr'] = $prev != 0 ? round(($curr - $prev) / abs($prev) * 100, 1) : null;
            }
        }

        return [
            'periods' => $periodsArr,
            'metrics' => $metrics,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Aggregate manual inputs within a date range
    // ─────────────────────────────────────────────────────────────────────────

    private function aggregateManualInputs($cid, $from, $to): array
    {
        // Get all manual inputs for this company and sum them
        // We use 'month' period type to match against the date range
        $result = ['da' => 0, 'interest' => 0, 'tax' => 0];

        $rows = ProfitabilityManualInput::where('portfolio_company_id', $cid)
            ->where('period_type', 'month')->get();

        foreach ($rows as $row) {
            // Convert YYYY-MM label to a date for range comparison
            $periodDate = $row->period_label . '-01';
            if ($periodDate >= $from && $periodDate <= $to) {
                $result['da']       += (float) $row->da_amount;
                $result['interest'] += (float) $row->interest_amount;
                $result['tax']      += (float) $row->tax_amount;
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Get manual inputs list formatted for UI
    // ─────────────────────────────────────────────────────────────────────────

    private function getManualInputsList($cid, $from, $to, $periodType): array
    {
        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($periodType);

        // Build all periods in range from sales/expense data
        $salesPeriods = SalesData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("$labelExpr as period, $sortExpr as sort_key")
            ->groupBy('period', 'sort_key')->orderBy('sort_key')
            ->pluck('period')->toArray();

        $expPeriods = ExpenseData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("$labelExpr as period, $sortExpr as sort_key")
            ->groupBy('period', 'sort_key')->orderBy('sort_key')
            ->pluck('period')->toArray();

        $allPeriods = array_unique(array_merge($salesPeriods, $expPeriods));

        // Get existing manual inputs
        $existing = ProfitabilityManualInput::where('portfolio_company_id', $cid)
            ->where('period_type', $periodType)
            ->whereIn('period_label', $allPeriods)
            ->get()->keyBy('period_label');

        $list = [];
        foreach ($allPeriods as $p) {
            $manual = $existing[$p] ?? null;
            $list[] = [
                'period_label'    => $p,
                'da_amount'       => $manual ? (float) $manual->da_amount       : 0,
                'interest_amount' => $manual ? (float) $manual->interest_amount : 0,
                'tax_amount'      => $manual ? (float) $manual->tax_amount      : 0,
                'notes'           => $manual ? $manual->notes                   : null,
            ];
        }

        return $list;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Period SQL expressions (same pattern as Expense/Sales modules)
    // ─────────────────────────────────────────────────────────────────────────

    private function getPeriodExpressions(string $period): array
    {
        return match($period) {
            'month'   => ["DATE_FORMAT(`date`, '%Y-%b')", "DATE_FORMAT(`date`, '%Y%m') + 0"],
            'quarter' => ["CONCAT(YEAR(`date`), '-Q', QUARTER(`date`))", "YEAR(`date`) * 10 + QUARTER(`date`)"],
            'semi'    => ["CONCAT(YEAR(`date`), '-H', IF(MONTH(`date`) <= 6, 1, 2))", "YEAR(`date`) * 10 + IF(MONTH(`date`) <= 6, 1, 2)"],
            'year'    => ["DATE_FORMAT(`date`, '%Y')", "YEAR(`date`)"],
            default   => ["DATE_FORMAT(`date`, '%Y-%b')", "DATE_FORMAT(`date`, '%Y%m') + 0"],
        };
    }


    // ─────────────────────────────────────────────────────────────────────────
    // AUTO INSIGHTS
    // ─────────────────────────────────────────────────────────────────────────

    public function insights(Request $request, PortfolioCompany $company)
    {
        $from     = $request->date_from;
        $to       = $request->date_to;
        $cid      = $company->id;
        $mappings = ProfitabilityPlMapping::where('portfolio_company_id', $cid)->pluck('pl_line', 'expense_category');
        return response()->json(['insights' => $this->buildProfitInsights($cid, $from, $to, $mappings)]);
    }

    private function buildProfitInsights($cid, $from, $to, $mappings): array
    {
        $insights = [];

        $revenue     = (float) SalesData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('net_sales_value');
        if ($revenue <= 0) return [['type' => 'warning', 'icon' => '⚠️', 'title' => 'No Revenue Data', 'body' => 'No sales data found for this period. Upload sales data to see profitability insights.']];

        $expenseRows = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_category')->selectRaw('expense_category, SUM(expense_amount) as total')
            ->groupBy('expense_category')->get();

        $byLine = ['cogs' => 0, 'opex' => 0, 'da' => 0, 'interest' => 0, 'tax' => 0, 'other' => 0];
        foreach ($expenseRows as $r) {
            $line = $mappings[$r->expense_category] ?? 'opex';
            $byLine[$line] += (float) $r->total;
        }

        $manuals     = $this->aggregateManualInputs($cid, $from, $to);
        $da          = $byLine['da']       + $manuals['da'];
        $interest    = $byLine['interest'] + $manuals['interest'];
        $tax         = $byLine['tax']      + $manuals['tax'];
        $grossProfit = $revenue - $byLine['cogs'];
        $ebitda      = $grossProfit - $byLine['opex'] - $byLine['other'];
        $ebit        = $ebitda - $da;
        $ebt         = $ebit - $interest;
        $netProfit   = $ebt - $tax;
        $pct         = fn($v) => $revenue > 0 ? round($v / $revenue * 100, 1) : 0;

        // ── Gross Margin ──
        $gm = $pct($grossProfit);
        if ($gm < 0) {
            $insights[] = ['type' => 'danger', 'icon' => '🚨', 'title' => 'Negative Gross Margin', 'body' => "Gross margin is {$gm}% — COGS exceeds revenue. Business is losing money on every sale before any overhead."];
        } elseif ($gm < 20) {
            $insights[] = ['type' => 'warning', 'icon' => '⚠️', 'title' => 'Low Gross Margin', 'body' => "Gross margin is {$gm}% — below the 20% threshold. Review COGS structure for optimization."];
        } elseif ($gm >= 50) {
            $insights[] = ['type' => 'positive', 'icon' => '💚', 'title' => 'Strong Gross Margin', 'body' => "Gross margin is {$gm}% — excellent. Strong pricing power and/or efficient cost of sales."];
        }

        // ── Net Profit ──
        $nm = $pct($netProfit);
        if ($netProfit < 0) {
            $insights[] = ['type' => 'danger', 'icon' => '🔴', 'title' => 'Net Loss', 'body' => "Net profit is negative at {$nm}% margin (" . number_format($netProfit, 0) . "). Business is operating at a loss for this period."];
        } elseif ($nm >= 15) {
            $insights[] = ['type' => 'positive', 'icon' => '🚀', 'title' => 'Strong Profitability', 'body' => "Net profit margin is {$nm}% — excellent bottom-line performance."];
        } elseif ($nm < 5 && $netProfit > 0) {
            $insights[] = ['type' => 'warning', 'icon' => '📉', 'title' => 'Thin Net Margins', 'body' => "Net profit margin is only {$nm}% — very little room for error. Any cost increase could push to losses."];
        }

        // ── EBITDA vs Gross Profit gap (OpEx efficiency) ──
        if ($grossProfit > 0) {
            $opexRatio = round(($byLine['opex'] + $byLine['other']) / $grossProfit * 100, 1);
            if ($opexRatio > 80) {
                $insights[] = ['type' => 'warning', 'icon' => '⚠️', 'title' => 'High OpEx Burden', 'body' => "Operating expenses consume {$opexRatio}% of gross profit — leaving little for D&A, interest, and tax. Streamline overhead."];
            }
        }

        // ── Margin compression month-over-month ──
        [$labelExpr, $sortExpr] = $this->getPeriodExpressions('month');
        $revenueByMonth = SalesData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->selectRaw("$labelExpr as period, $sortExpr as sk, SUM(net_sales_value) as rev")
            ->groupBy('period', 'sk')->orderBy('sk')->get();

        if ($revenueByMonth->count() >= 2) {
            $expByMonth = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
                ->selectRaw("$labelExpr as period, SUM(expense_amount) as total")
                ->groupBy('period')->pluck('total', 'period');

            $margins = [];
            foreach ($revenueByMonth as $r) {
                $rev  = (float)$r->rev;
                $exp  = (float)($expByMonth[$r->period] ?? 0);
                $net  = $rev - $exp;
                $margins[$r->period] = $rev > 0 ? round($net / $rev * 100, 1) : 0;
            }
            $margArr   = array_values($margins);
            $lastM     = end($margArr);
            $prevM     = $margArr[count($margArr) - 2];
            $margDrop  = round($prevM - $lastM, 1);
            $lastPer   = array_key_last($margins);
            if ($margDrop >= 10) {
                $insights[] = ['type' => 'danger', 'icon' => '📉', 'title' => 'Margin Compression', 'body' => "Net margin dropped {$margDrop}pp in {$lastPer} vs previous month — significant profitability deterioration."];
            } elseif ($margDrop >= 5) {
                $insights[] = ['type' => 'warning', 'icon' => '⚠️', 'title' => 'Margin Tightening', 'body' => "Net margin narrowed {$margDrop}pp in {$lastPer} — watch for continued compression."];
            }
        }

        // ── COGS missing warning ──
        if ($byLine['cogs'] == 0) {
            $insights[] = ['type' => 'warning', 'icon' => '🗂️', 'title' => 'No COGS Mapped', 'body' => 'No expense categories are mapped to COGS. Gross Profit equals Revenue. Map your cost-of-goods categories in P&L Mapping for accurate margins.'];
        }

        return $insights;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NOTES — Save / Get / Update / Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function saveNote(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
            'note'      => ['required', 'string', 'max:50000'],
        ]);

        DB::table('profitability_dashboard_notes')->insert([
            'portfolio_company_id' => $company->id,
            'date_from'            => $request->date_from,
            'date_to'              => $request->date_to,
            'note'                 => $request->note,
            'created_by'           => Auth::id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function getNotes(Request $request, PortfolioCompany $company)
    {
        $notes = DB::table('profitability_dashboard_notes')
            ->where('portfolio_company_id', $company->id)
            ->where('date_from', $request->date_from)
            ->where('date_to',   $request->date_to)
            ->join('users', 'users.id', '=', 'profitability_dashboard_notes.created_by')
            ->select('profitability_dashboard_notes.*', 'users.name as author')
            ->orderByDesc('profitability_dashboard_notes.updated_at')->get();

        return response()->json(['notes' => $notes]);
    }

    public function updateNote(Request $request, PortfolioCompany $company, $noteId)
    {
        $request->validate(['note' => ['required', 'string', 'max:50000']]);
        DB::table('profitability_dashboard_notes')
            ->where('id', $noteId)->where('portfolio_company_id', $company->id)->where('created_by', Auth::id())
            ->update(['note' => $request->note, 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function deleteNote(PortfolioCompany $company, $noteId)
    {
        DB::table('profitability_dashboard_notes')
            ->where('id', $noteId)->where('portfolio_company_id', $company->id)->where('created_by', Auth::id())
            ->delete();
        return response()->json(['success' => true]);
    }
}