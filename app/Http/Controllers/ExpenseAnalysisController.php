<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PortfolioCompany;
use App\Models\ExpenseUpload;
use App\Models\ExpenseData;
use App\Models\SalesData;
use App\Imports\ExpenseDataImport;
use App\Exports\ExpenseTemplateExport;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseAnalysisController extends Controller
{
    private function authorizeExpense(PortfolioCompany $company): PortfolioCompany
    {
        return $this->authorizeCompany($company, 'expense_analysis');
    }

    private function friendlyUploadError(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }
        if (str_contains($raw, 'Column not found') || str_contains($raw, 'SQLSTATE')) {
            return 'Import failed due to a data format issue. Please check your file and try again.';
        }
        return strlen($raw) > 200 ? substr($raw, 0, 200) . '…' : $raw;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPLOAD PAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function uploadPage(PortfolioCompany $company)
    {
        $this->authorizeExpense($company);
        $uploads = ExpenseUpload::where('portfolio_company_id', $company->id)
            ->orderByDesc('created_at')->get()
            ->map(fn($u) => [
                'id'            => $u->id,
                'period_from'   => $u->period_from?->format('Y-m-d'),
                'period_to'     => $u->period_to?->format('Y-m-d'),
                'date_format'   => $u->date_format,
                'row_count'     => $u->row_count,
                'status'        => $u->status,
                'error_message' => $this->friendlyUploadError($u->error_message),
                'created_at'    => $u->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('ExpenseAnalysis/Upload', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'uploads' => $uploads,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROCESS UPLOAD
    // ─────────────────────────────────────────────────────────────────────────

    public function processUpload(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'file'        => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
            'date_format' => ['required', 'in:DD/MM/YYYY,MM/DD/YYYY,YYYY/MM/DD,DD-MM-YYYY,MM-DD-YYYY,YYYY-MM-DD'],
        ]);

        $path   = $request->file('file')->store('expense-uploads', 'public');
        $upload = ExpenseUpload::create([
            'portfolio_company_id' => $company->id,
            'uploaded_by'          => Auth::id(),
            'file_path'            => $path,
            'period_from'          => $request->period_from,
            'period_to'            => $request->period_to,
            'date_format'          => $request->date_format,
            'row_count'            => 0,
            'status'               => 'processing',
        ]);

        // try {
        //     $importer = new ExpenseDataImport($company->id, $upload->id, $request->date_format);
        //     Excel::import($importer, storage_path('app/public/' . $path));
        //     $upload->update(['row_count' => $importer->getRowCount(), 'status' => 'completed']);
        // } catch (\Exception $e) {
        //     $upload->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        // }

        \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\ProcessExpenseUpload($upload->id));

        return redirect()->route('expense.upload', $company->id)
            ->with('flash', ['success' => 'File uploaded successfully!']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOAD TEMPLATE
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadTemplate(PortfolioCompany $company)
    {
        return Excel::download(new ExpenseTemplateExport(), 'Expense_Template.xlsx');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPORTS PAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function reportsPage(PortfolioCompany $company)
    {
        $hasData = ExpenseData::where('portfolio_company_id', $company->id)->exists();
        if (!$hasData) return redirect()->route('expense.upload', $company->id);

        $minDate    = ExpenseData::where('portfolio_company_id', $company->id)->min('date');
        $maxDate    = ExpenseData::where('portfolio_company_id', $company->id)->max('date');
        $categories = ExpenseData::where('portfolio_company_id', $company->id)
            ->whereNotNull('expense_category')->distinct()->pluck('expense_category')->sort()->values();

        return Inertia::render('ExpenseAnalysis/Reports', [
            'company'    => ['id' => $company->id, 'name' => $company->name],
            'minDate'    => $minDate,
            'maxDate'    => $maxDate,
            'categories' => $categories,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RUN REPORT
    // ─────────────────────────────────────────────────────────────────────────

    public function runReport(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'report_type' => ['required', 'in:category_breakdown,subcategory_breakdown,item_breakdown,trend,min_avg_max,period_comparison'],
            'date_from'   => ['required', 'date'],
            'date_to'     => ['required', 'date'],
        ]);

        $cid  = $company->id;
        $from = $request->date_from;
        $to   = $request->date_to;
        $type = $request->report_type;

        $totalExpense = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('expense_amount');
        $totalRevenue = SalesData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('net_sales_value');

        $result = match($type) {
            'category_breakdown'    => $this->categoryBreakdown($cid, $from, $to, $totalExpense, $totalRevenue),
            'subcategory_breakdown' => $this->subcategoryBreakdown($cid, $from, $to, $totalExpense, $totalRevenue),
            'item_breakdown'        => $this->itemBreakdown($cid, $from, $to, $totalExpense, $totalRevenue, $request->category),
            'trend'                 => $this->trendReport($cid, $from, $to, $request->category, $request->period ?? 'monthly'),
            'min_avg_max'           => $this->minAvgMaxReport($cid, $from, $to, $request->category),
            'period_comparison'     => $this->periodComparison($cid, $request),
        };

        return response()->json([
            'result'        => $result,
            'total_expense' => $totalExpense,
            'total_revenue' => $totalRevenue,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORT REPORT
    // ─────────────────────────────────────────────────────────────────────────

    public function exportReport(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'report_type' => ['required'],
            'date_from'   => ['required', 'date'],
            'date_to'     => ['required', 'date'],
        ]);

        try {
        $cid          = $company->id;
        $from         = $request->date_from;
        $to           = $request->date_to;
        $type         = $request->report_type;
        $totalExpense = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('expense_amount');
        $totalRevenue = SalesData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('net_sales_value');

        $data = match($type) {
            'category_breakdown'    => $this->categoryBreakdown($cid, $from, $to, $totalExpense, $totalRevenue),
            'subcategory_breakdown' => $this->subcategoryBreakdown($cid, $from, $to, $totalExpense, $totalRevenue),
            'item_breakdown'        => $this->itemBreakdown($cid, $from, $to, $totalExpense, $totalRevenue, $request->category),
            'trend'                 => $this->trendReport($cid, $from, $to, $request->category, $request->period ?? 'monthly'),
            'min_avg_max'           => $this->minAvgMaxReport($cid, $from, $to, $request->category),
            'period_comparison'     => $this->periodComparison($cid, $request),
            default                 => [],
        };

        $export   = new \App\Exports\ExpenseReportExport($company->name, $type, $from, $to, $data, $totalExpense, $totalRevenue);
        $filename = $company->name . '_Expense_' . $type . '_' . $from . '_' . $to . '.xlsx';

        return Excel::download($export, $filename);
        } catch (\Throwable $e) {
            \Log::error('Expense export failed: ' . $e->getMessage(), ['exception' => $e]);
            return back()->withErrors(['export' => 'Export failed. Please try again or contact support.']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BREAKEVEN PAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function breakevenPage(PortfolioCompany $company)
    {
        $hasExpense = ExpenseData::where('portfolio_company_id', $company->id)->exists();
        if (!$hasExpense) return redirect()->route('expense.upload', $company->id);

        $maxDate     = ExpenseData::where('portfolio_company_id', $company->id)->max('date');
        $minDate     = ExpenseData::where('portfolio_company_id', $company->id)->min('date');
        $defaultFrom = date('Y', strtotime($maxDate)) . '-01-01';

        return Inertia::render('ExpenseAnalysis/Breakeven', [
            'company'     => ['id' => $company->id, 'name' => $company->name],
            'defaultFrom' => $defaultFrom,
            'defaultTo'   => $maxDate,
            'minDate'     => $minDate,
            'maxDate'     => $maxDate,
            'hasSales'    => SalesData::where('portfolio_company_id', $company->id)->exists(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BREAKEVEN CALCULATE (AJAX)
    //
    // HOW IT WORKS — Pearson Correlation Coefficient:
    //   For each expense item we collect monthly pairs: (expense_amount, revenue)
    //   We compute r = Pearson(expense, revenue) across all months.
    //
    //   r >= 0.65  → expense MOVES with revenue  → VARIABLE cost
    //   r <  0.65  → expense is INDEPENDENT       → FIXED cost
    //
    // Then:
    //   CM Ratio        = (Revenue - Variable Costs) / Revenue
    //   Breakeven       = Fixed Costs / CM Ratio
    //   Safety Margin   = Actual Revenue - Breakeven Revenue
    // ─────────────────────────────────────────────────────────────────────────

    public function breakevenCalculate(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
        ]);

        $from = $request->date_from;
        $to   = $request->date_to;
        $cid  = $company->id;

        // Monthly revenue lookup
        $revenueByMonth = SalesData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(net_sales_value) as rev")
            ->groupBy('month')->pluck('rev', 'month')
            ->map(fn($v) => (float) $v)->toArray();

        // Monthly expense per item
        $monthly = ExpenseData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])->whereNotNull('expense_name')
            ->selectRaw("expense_category, expense_name, DATE_FORMAT(date, '%Y-%m') as month, SUM(expense_amount) as total")
            ->groupBy('expense_category', 'expense_name', 'month')->get();

        $grouped = $monthly->groupBy(fn($r) => $r->expense_category . '|||' . $r->expense_name);

        $items         = [];
        $fixedTotal    = 0;
        $variableTotal = 0;

        foreach ($grouped as $key => $rows) {
            [$cat, $item] = explode('|||', $key);

            // Build paired vectors for months that have both expense and revenue
            $expenseVec = [];
            $revenueVec = [];
            foreach ($rows as $r) {
                if (isset($revenueByMonth[$r->month])) {
                    $expenseVec[] = (float) $r->total;
                    $revenueVec[] = $revenueByMonth[$r->month];
                }
            }

            $correlation = $this->pearsonCorrelation($expenseVec, $revenueVec);
            $itemTotal   = $rows->sum(fn($r) => (float) $r->total);

            // r >= 0.65 → Variable (moves with sales), otherwise Fixed
            $nature = ($correlation !== null && $correlation >= 0.65) ? 'variable' : 'fixed';

            if ($nature === 'variable') {
                $variableTotal += $itemTotal;
            } else {
                $fixedTotal += $itemTotal;
            }

            $items[] = [
                'category'    => $cat,
                'item'        => $item,
                'total'       => round($itemTotal, 2),
                'correlation' => $correlation !== null ? round($correlation, 3) : null,
                'nature'      => $nature,
            ];
        }

        usort($items, fn($a, $b) => $a['category'] <=> $b['category'] ?: $b['total'] <=> $a['total']);

        $totalRevenue    = array_sum($revenueByMonth);
        $totalExpense    = $fixedTotal + $variableTotal;
        $cmRatio         = $totalRevenue > 0 ? ($totalRevenue - $variableTotal) / $totalRevenue : null;
        $breakevenRev    = ($cmRatio !== null && $cmRatio > 0) ? $fixedTotal / $cmRatio : null;
        $breakevenPct    = ($breakevenRev !== null && $totalRevenue > 0) ? round($breakevenRev / $totalRevenue * 100, 1) : null;
        $safetyMargin    = $breakevenRev !== null ? $totalRevenue - $breakevenRev : null;
        $safetyMarginPct = ($safetyMargin !== null && $totalRevenue > 0) ? round($safetyMargin / $totalRevenue * 100, 1) : null;

        return response()->json([
            'items'             => $items,
            'fixed_total'       => round($fixedTotal, 2),
            'variable_total'    => round($variableTotal, 2),
            'total_expense'     => round($totalExpense, 2),
            'total_revenue'     => round($totalRevenue, 2),
            'cm_ratio'          => $cmRatio !== null ? round($cmRatio * 100, 2) : null,
            'breakeven_revenue' => $breakevenRev !== null ? round($breakevenRev, 2) : null,
            'breakeven_pct'     => $breakevenPct,
            'safety_margin'     => $safetyMargin !== null ? round($safetyMargin, 2) : null,
            'safety_margin_pct' => $safetyMarginPct,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD PAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboardPage(PortfolioCompany $company)
    {
        $hasData = ExpenseData::where('portfolio_company_id', $company->id)->exists();
        if (!$hasData) return redirect()->route('expense.upload', $company->id);

        $maxDate     = ExpenseData::where('portfolio_company_id', $company->id)->max('date');
        $minDate     = ExpenseData::where('portfolio_company_id', $company->id)->min('date');
        $defaultFrom = date('Y', strtotime($maxDate)) . '-01-01';

        return Inertia::render('ExpenseAnalysis/Dashboard', [
            'company'     => ['id' => $company->id, 'name' => $company->name],
            'defaultFrom' => $defaultFrom,
            'defaultTo'   => $maxDate,
            'minDate'     => $minDate,
            'maxDate'     => $maxDate,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD DATA (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboardData(Request $request, PortfolioCompany $company)
    {
        $from = $request->date_from ?? date('Y-01-01');
        $to   = $request->date_to   ?? date('Y-m-d');
        $cid  = $company->id;

        $totalExpense  = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('expense_amount');
        $totalRevenue  = SalesData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('net_sales_value');
        $categoryCount = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->whereNotNull('expense_category')->distinct('expense_category')->count();
        $itemCount     = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->whereNotNull('expense_name')->distinct('expense_name')->count();
        $avgMonthly    = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(expense_amount) as total")->groupBy('month')->get()->avg('total');

        // Category Breakdown — category/total/pct (keys match both Vue table and Chart.js donut)
        $categoryBreakdown = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_category')->groupBy('expense_category')
            ->selectRaw('expense_category, SUM(expense_amount) as total')
            ->orderByRaw('SUM(expense_amount) DESC')->get()
            ->map(fn($r) => [
                'category' => $r->expense_category,
                'total'    => (float) $r->total,
                'pct'      => $totalExpense > 0 ? round($r->total / $totalExpense * 100, 1) : 0,
            ]);

        // Monthly trend — same structure as sales dashboard (period, value, growth_rate)
        $monthlyRows    = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(expense_amount) as total")
            ->groupBy('month')->orderBy('month')->get();

        $monthlyRevenue = SalesData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(net_sales_value) as rev_total")
            ->groupBy('month')->pluck('rev_total', 'month');

        $trendData = $monthlyRows->map(function ($r, $i) use ($monthlyRows, $monthlyRevenue) {
            $expense = (float) $r->total;
            $rev     = (float) ($monthlyRevenue[$r->month] ?? 0);
            $prev    = $i > 0 ? (float) $monthlyRows[$i - 1]->total : null;
            $gr      = ($prev !== null && $prev > 0) ? round(($expense - $prev) / $prev * 100, 1) : null;
            return [
                'period'      => $r->month,
                'value'       => $expense,
                'growth_rate' => $gr,
                'revenue_pct' => $rev > 0 ? round($expense / $rev * 100, 1) : 0,
            ];
        });

        // Top 10 items
        $topItems = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_name')->groupBy('expense_category', 'expense_name')
            ->selectRaw('expense_category, expense_name, SUM(expense_amount) as total')
            ->orderByRaw('SUM(expense_amount) DESC')->limit(10)->get()
            ->map(fn($r) => [
                'category' => $r->expense_category,
                'item'     => $r->expense_name,
                'total'    => (float) $r->total,
                'pct'      => $totalExpense > 0 ? round($r->total / $totalExpense * 100, 1) : 0,
            ]);

        // Stats per category
        $statsPerCategory = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_category')
            ->selectRaw("expense_category, MIN(expense_amount) as min_val, AVG(expense_amount) as avg_val, MAX(expense_amount) as max_val, SUM(expense_amount) as total")
            ->groupBy('expense_category')->orderByRaw('SUM(expense_amount) DESC')->get()
            ->map(fn($r) => [
                'category' => $r->expense_category,
                'min'      => round((float) $r->min_val, 2),
                'avg'      => round((float) $r->avg_val, 2),
                'max'      => round((float) $r->max_val, 2),
                'total'    => round((float) $r->total, 2),
            ]);

        $runRate = $this->computeRunRateProjection($cid, $from, $to);

        return response()->json([
            'kpis' => [
                'total_expense'  => (float) $totalExpense,
                'total_revenue'  => (float) $totalRevenue,
                'expense_to_rev' => $totalRevenue > 0 ? round($totalExpense / $totalRevenue * 100, 1) : 0,
                'category_count' => $categoryCount,
                'item_count'     => $itemCount,
                'avg_monthly'    => round((float) $avgMonthly, 2),
            ],
            'category_breakdown' => $categoryBreakdown,
            'monthly_trend'      => $trendData,
            'top_items'          => $topItems,
            'stats_per_category' => $statsPerCategory,
            'run_rate'           => $runRate,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RUN-RATE PROJECTION
    // Only meaningful for a year-to-date range (from = Jan 1, to = some
    // date before Dec 31 of that same year) — that's the standard framing
    // of a "run rate" and the only case where "project the rest of the
    // year" makes sense. Any other date range returns applicable=false and
    // the frontend simply hides the card.
    //
    // Method C (default): YTD actual + (remaining months × a trailing
    // 3-month average, with any IQR outlier months excluded from that
    // average so a one-off spike doesn't get annualized).
    //
    // Method D (used automatically once 2 full prior calendar years of
    // data exist): YTD actual ÷ the average share of the full year that
    // prior years had typically reached by this same point — this is
    // what correctly inflates the projection for a business where a big
    // chunk of spend is seasonally loaded into specific months (e.g. a
    // December bonus payout) rather than assuming a flat pace.
    // ─────────────────────────────────────────────────────────────────────────

    private function computeRunRateProjection($cid, $from, $to): array
    {
        $fromDate = \Carbon\Carbon::parse($from);
        $toDate   = \Carbon\Carbon::parse($to);
        $year     = $fromDate->year;
        $yearEnd  = \Carbon\Carbon::create($year, 12, 31);

        if (!$fromDate->isSameDay(\Carbon\Carbon::create($year, 1, 1))) {
            return ['applicable' => false, 'reason' => 'not_ytd'];
        }
        if ($toDate->greaterThanOrEqualTo($yearEnd)) {
            return ['applicable' => false, 'reason' => 'full_year'];
        }

        $monthlyRows = ExpenseData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(expense_amount) as total")
            ->groupBy('month')->orderBy('month')->get();

        $monthsElapsed = $monthlyRows->count();
        if ($monthsElapsed === 0) {
            return ['applicable' => false, 'reason' => 'no_data'];
        }
        $remainingMonths = 12 - $monthsElapsed;
        if ($remainingMonths <= 0) {
            return ['applicable' => false, 'reason' => 'full_year'];
        }

        $ytdActual = (float) $monthlyRows->sum('total');
        $values = $monthlyRows->pluck('total')->map(fn($v) => (float) $v)->values();

        // ── Identify outlier months (IQR, same method as Reports/Comparison
        // Dashboard) so a one-off spike doesn't distort the trailing average.
        $outlierMonths = [];
        if ($values->count() >= 4) {
            $sorted = $values->sort()->values()->toArray();
            $q1 = $this->percentile($sorted, 25);
            $q3 = $this->percentile($sorted, 75);
            $iqr = $q3 - $q1;
            $lowerFence = $q1 - 1.5 * $iqr;
            $upperFence = $q3 + 1.5 * $iqr;
            foreach ($monthlyRows as $row) {
                if ((float) $row->total > $upperFence || (float) $row->total < $lowerFence) {
                    $outlierMonths[] = $row->month;
                }
            }
        }

        // ── Method C: trailing 3 months, outliers excluded ──
        $trailing = $monthlyRows->reverse()->values()->take(3)
            ->filter(fn($r) => !in_array($r->month, $outlierMonths));
        // If excluding outliers left nothing (e.g. all 3 recent months were
        // flagged), fall back to every non-outlier month available rather
        // than silently using an outlier anyway.
        if ($trailing->isEmpty()) {
            $trailing = $monthlyRows->filter(fn($r) => !in_array($r->month, $outlierMonths));
        }
        // If EVERY month is somehow an outlier (degenerate case), fall back
        // to the plain average of everything rather than returning nothing.
        if ($trailing->isEmpty()) {
            $trailing = $monthlyRows;
        }
        $trailingAvg = (float) $trailing->avg('total');
        $projectedFullYearC = $ytdActual + ($trailingAvg * $remainingMonths);

        $result = [
            'applicable'          => true,
            'method'              => 'trend',
            'year'                => $year,
            'as_of'               => $to,
            'months_elapsed'      => $monthsElapsed,
            'months_remaining'    => $remainingMonths,
            'ytd_actual'          => round($ytdActual, 2),
            'trailing_avg'        => round($trailingAvg, 2),
            'trailing_months_used'=> $trailing->pluck('month')->values()->all(),
            'outlier_months_excluded' => $outlierMonths,
            'projected_full_year' => round($projectedFullYearC, 2),
            'projected_remainder' => round($projectedFullYearC - $ytdActual, 2),
            'years_of_history_used' => 0,
        ];

        // ── Method D: seasonal, only if 2 full prior calendar years exist ──
        $candidateYears = [$year - 1, $year - 2];
        $fullPriorYears = [];
        foreach ($candidateYears as $y) {
            $monthsPresent = (int) ExpenseData::where('portfolio_company_id', $cid)
                ->whereBetween('date', ["{$y}-01-01", "{$y}-12-31"])
                ->selectRaw("COUNT(DISTINCT DATE_FORMAT(date, '%Y-%m')) as month_count")
                ->value('month_count');
            if ($monthsPresent === 12) $fullPriorYears[] = $y;
        }

        if (count($fullPriorYears) >= 2) {
            $ratios = [];
            foreach ($fullPriorYears as $y) {
                $yearTotal = (float) ExpenseData::where('portfolio_company_id', $cid)
                    ->whereBetween('date', ["{$y}-01-01", "{$y}-12-31"])->sum('expense_amount');
                $cutoff = \Carbon\Carbon::create($y, $toDate->month, 1)->endOfMonth();
                if ($toDate->day < $cutoff->day) $cutoff->setDay($toDate->day);
                $elapsedTotal = (float) ExpenseData::where('portfolio_company_id', $cid)
                    ->whereBetween('date', ["{$y}-01-01", $cutoff->format('Y-m-d')])->sum('expense_amount');
                if ($yearTotal > 0) $ratios[] = $elapsedTotal / $yearTotal;
            }

            if (count($ratios) >= 2) {
                $avgRatio = array_sum($ratios) / count($ratios);
                if ($avgRatio > 0) {
                    $projectedFullYearD = $ytdActual / $avgRatio;
                    $result['method']                 = 'seasonal';
                    $result['projected_full_year']     = round($projectedFullYearD, 2);
                    $result['projected_remainder']     = round($projectedFullYearD - $ytdActual, 2);
                    $result['years_of_history_used']   = count($fullPriorYears);
                    $result['seasonal_years']          = $fullPriorYears;
                    $result['seasonal_pct_elapsed_by_now'] = round($avgRatio * 100, 1);
                    // Keep the Method C figures too, so the UI can show
                    // both and be transparent about which one is driving
                    // the headline number.
                    $result['trend_method_full_year'] = round($projectedFullYearC, 2);
                }
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Category Breakdown
    // ─────────────────────────────────────────────────────────────────────────

    private function categoryBreakdown($cid, $from, $to, $te, $tr): array
    {
        return ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_category')->groupBy('expense_category')
            ->selectRaw('expense_category, SUM(expense_amount) as total')
            ->orderByRaw('SUM(expense_amount) DESC')->get()
            ->map(fn($r) => [
                'category'       => $r->expense_category,
                'total'          => (float) $r->total,
                'pct_of_expense' => $te > 0 ? round($r->total / $te * 100, 2) : 0,
                'pct_of_revenue' => $tr > 0 ? round($r->total / $tr * 100, 2) : 0,
            ])->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Sub-Category Breakdown
    // ─────────────────────────────────────────────────────────────────────────

    private function subcategoryBreakdown($cid, $from, $to, $te, $tr): array
    {
        return ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_sub_category')->groupBy('expense_category', 'expense_sub_category')
            ->selectRaw('expense_category, expense_sub_category, SUM(expense_amount) as total')
            ->orderBy('expense_category')->orderByRaw('SUM(expense_amount) DESC')->get()
            ->map(fn($r) => [
                'category'       => $r->expense_category,
                'sub_category'   => $r->expense_sub_category,
                'total'          => (float) $r->total,
                'pct_of_expense' => $te > 0 ? round($r->total / $te * 100, 2) : 0,
                'pct_of_revenue' => $tr > 0 ? round($r->total / $tr * 100, 2) : 0,
            ])->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Item Breakdown
    // ─────────────────────────────────────────────────────────────────────────

    private function itemBreakdown($cid, $from, $to, $te, $tr, $category = null): array
    {
        $query = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->whereNotNull('expense_name');
        if ($category) $query->where('expense_category', $category);

        return $query->groupBy('expense_category', 'expense_name')
            ->selectRaw('expense_category, expense_name, SUM(expense_amount) as total')
            ->orderBy('expense_category')->orderByRaw('SUM(expense_amount) DESC')->get()
            ->map(fn($r) => [
                'category'       => $r->expense_category,
                'item'           => $r->expense_name,
                'total'          => (float) $r->total,
                'pct_of_expense' => $te > 0 ? round($r->total / $te * 100, 2) : 0,
                'pct_of_revenue' => $tr > 0 ? round($r->total / $tr * 100, 2) : 0,
            ])->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Trend Report
    // Mirrors sales two_factors_trend structure exactly:
    //   - periods array (column headers — BEFORE Total)
    //   - each row has cells[period] = {value, gr}  and  total at END
    //   - parent = category rows, children = item rows (collapsible)
    // ─────────────────────────────────────────────────────────────────────────

    private function trendReport($cid, $from, $to, $category = null, $period = 'monthly'): array
    {
        $query = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to]);
        if ($category) $query->where('expense_category', $category);

        [$labelExpr, $sortExpr] = $this->getPeriodExpressions($period);

        $rows = $query
            ->selectRaw("expense_category, expense_name, $labelExpr as period_label, $sortExpr as sort_key, SUM(expense_amount) as total")
            ->groupBy('expense_category', 'expense_name', 'period_label', 'sort_key')
            ->orderBy('expense_category')->orderBy('expense_name')->orderBy('sort_key')->get();

        $periods = $rows->sortBy('sort_key')->pluck('period_label')->unique()->values()->toArray();

        // Build tree: category → items → period data
        $tree = [];
        foreach ($rows as $r) {
            $cat  = $r->expense_category ?? 'Uncategorized';
            $item = $r->expense_name     ?? 'Unnamed';
            if (!isset($tree[$cat])) {
                $tree[$cat] = ['label' => $cat, 'total' => 0, 'rawCells' => [], 'cells' => [], 'children' => []];
            }
            if (!isset($tree[$cat]['children'][$item])) {
                $tree[$cat]['children'][$item] = ['label' => $item, 'total' => 0, 'rawCells' => [], 'cells' => []];
            }
            $tree[$cat]['children'][$item]['rawCells'][$r->period_label] = (float) $r->total;
            $tree[$cat]['children'][$item]['total'] += (float) $r->total;
            $tree[$cat]['rawCells'][$r->period_label] = ($tree[$cat]['rawCells'][$r->period_label] ?? 0) + (float) $r->total;
            $tree[$cat]['total'] += (float) $r->total;
        }

        // Compute GR% — same exact logic as sales two_factors_trend
        foreach ($tree as &$cat) {
            $prevVal = null;
            foreach ($periods as $p) {
                $val             = $cat['rawCells'][$p] ?? 0;
                $gr              = ($prevVal !== null && $prevVal > 0) ? round(($val - $prevVal) / $prevVal * 100, 1) : 0;
                $cat['cells'][$p] = ['value' => $val, 'gr' => $gr];
                $prevVal = $val;
            }
            foreach ($cat['children'] as &$child) {
                $prevVal = null;
                foreach ($periods as $p) {
                    $val                = $child['rawCells'][$p] ?? 0;
                    $gr                 = ($prevVal !== null && $prevVal > 0) ? round(($val - $prevVal) / $prevVal * 100, 1) : 0;
                    $child['cells'][$p] = ['value' => $val, 'gr' => $gr];
                    $prevVal = $val;
                }
                unset($child['rawCells']);
            }
            $cat['children'] = array_values($cat['children']);
            unset($cat['rawCells']);
        }

        return [
            'type'    => 'trend',
            'period'  => $period,
            'periods' => $periods,  // ← column headers, Total is always LAST in the Vue template
            'rows'    => array_values($tree),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Period Comparison (mirrors sales exactly)
    // ─────────────────────────────────────────────────────────────────────────

    private function periodComparison($cid, $request): array
    {
        $dim    = $request->compare_by ?? 'category';
        $colMap = ['category' => 'expense_category', 'sub_category' => 'expense_sub_category', 'item' => 'expense_name'];
        $col    = $colMap[$dim] ?? 'expense_category';

        // 2–5 periods, sent as `periods` = a JSON array of {from, to} pairs.
        // Falls back to the old date_from/date_to + compare_from/compare_to
        // pair so older callers (e.g. a stale export link) still work.
        $periods = [];
        $rawPeriods = $request->input('periods');
        if (is_string($rawPeriods)) $rawPeriods = json_decode($rawPeriods, true);
        if (is_array($rawPeriods)) {
            foreach ($rawPeriods as $p) {
                if (!empty($p['from']) && !empty($p['to'])) {
                    $periods[] = ['from' => $p['from'], 'to' => $p['to']];
                }
            }
        }
        if (empty($periods)) {
            $periods[] = ['from' => $request->date_from, 'to' => $request->date_to];
            if ($request->filled('compare_from') && $request->filled('compare_to')) {
                $periods[] = ['from' => $request->compare_from, 'to' => $request->compare_to];
            }
        }
        // Always at least 1, never more than 5.
        $periods = array_slice($periods, 0, 5);

        $periodTotals = array_map(function ($p) use ($cid, $col) {
            return ExpenseData::where('portfolio_company_id', $cid)
                ->whereBetween('date', [$p['from'], $p['to']])
                ->whereNotNull($col)->where($col, '!=', '')
                ->selectRaw("`$col` as label, SUM(expense_amount) as value")
                ->groupBy($col)->get()->keyBy('label');
        }, $periods);

        $allLabels = collect($periodTotals)->flatMap(fn($c) => $c->keys())->unique()->values();

        $rows = $allLabels->map(function ($label) use ($periodTotals) {
            $values = array_map(fn($pt) => (float) ($pt[$label]->value ?? 0), $periodTotals);

            // % change vs the immediately preceding period — one fewer
            // entry than $values, aligned to values[1], values[2], ...
            $changes = [];
            for ($i = 1; $i < count($values); $i++) {
                $changes[] = $values[$i - 1] > 0
                    ? round(($values[$i] - $values[$i - 1]) / $values[$i - 1] * 100, 2)
                    : null;
            }

            return [
                'label'   => $label,
                'values'  => $values,
                'changes' => $changes,
            ];
        })->sortByDesc(fn($r) => $r['values'][0])->values();

        return [
            'type'    => 'period_comparison',
            'periods' => $periods,
            'rows'    => $rows,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: Min / Avg / Max & Outliers
    // ─────────────────────────────────────────────────────────────────────────

    private function minAvgMaxReport($cid, $from, $to, $category = null): array
    {
        $query = ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to]);
        if ($category) $query->where('expense_category', $category);

        $monthly = $query->clone()
            ->selectRaw("expense_category, expense_name, DATE_FORMAT(date, '%Y-%m') as month, SUM(expense_amount) as monthly_total")
            ->groupBy('expense_category', 'expense_name', 'month')->get();

        $grouped = $monthly->groupBy(fn($r) => $r->expense_category . '|||' . $r->expense_name);
        $results = [];

        foreach ($grouped as $key => $rows) {
            [$cat, $item] = explode('|||', $key);
            $values = $rows->pluck('monthly_total')->map(fn($v) => (float) $v)->sort()->values();
            $count  = $values->count();
            $avg    = $values->avg();
            $std    = $this->stdDev($values->toArray());

            // IQR (Tukey's fences) instead of a flat 1.5×std-dev band: with
            // only a handful of months, std-dev is easily skewed by normal
            // growth/seasonality, so a fixed z-score threshold ends up
            // flagging the highest and lowest month of almost every item —
            // even ones that are just trending steadily up, not anomalous.
            // IQR only flags points genuinely far outside the item's typical
            // spread, and we skip it entirely below 4 months since quartiles
            // aren't meaningful on that little data.
            $outlierMonths = [];
            if ($count >= 4) {
                $sortedVals = $values->toArray();
                $q1 = $this->percentile($sortedVals, 25);
                $q3 = $this->percentile($sortedVals, 75);
                $iqr = $q3 - $q1;
                $lowerFence = $q1 - 1.5 * $iqr;
                $upperFence = $q3 + 1.5 * $iqr;

                $outlierMonths = $rows->filter(fn($r) =>
                    (float)$r->monthly_total > $upperFence ||
                    (float)$r->monthly_total < $lowerFence
                )->map(fn($r) => ['month' => $r->month, 'value' => (float)$r->monthly_total])
                 ->values()->toArray();
            }

            $results[] = [
                'category'       => $cat,
                'item'           => $item,
                'months_count'   => $count,
                'min'            => round($values->min(), 2),
                'avg'            => round($avg, 2),
                'max'            => round($values->max(), 2),
                'std_dev'        => round($std, 2),
                'outlier_count'  => count($outlierMonths),
                'outlier_months' => $outlierMonths,
            ];
        }

        usort($results, fn($a, $b) => $a['category'] <=> $b['category'] ?: $b['avg'] <=> $a['avg']);
        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getPeriodExpressions(string $period): array
    {
        return match($period) {
            'monthly'       => ["DATE_FORMAT(`date`, '%Y-%b')", "DATE_FORMAT(`date`, '%Y%m') + 0"],
            'quarterly'     => ["CONCAT(YEAR(`date`), '-Q', QUARTER(`date`))", "YEAR(`date`) * 10 + QUARTER(`date`)"],
            'semi_annually' => ["CONCAT(YEAR(`date`), '-H', IF(MONTH(`date`) <= 6, 1, 2))", "YEAR(`date`) * 10 + IF(MONTH(`date`) <= 6, 1, 2)"],
            'annually'      => ["DATE_FORMAT(`date`, '%Y')", "YEAR(`date`)"],
            default         => ["DATE_FORMAT(`date`, '%Y-%b')", "DATE_FORMAT(`date`, '%Y%m') + 0"],
        };
    }

    /**
     * Linear-interpolation percentile of a SORTED array of numbers
     * (e.g. percentile($sorted, 25) for Q1, percentile($sorted, 75) for Q3).
     */
    private function percentile(array $sortedValues, float $pct): float
    {
        $count = count($sortedValues);
        if ($count === 0) return 0;
        if ($count === 1) return $sortedValues[0];

        $rank = ($pct / 100) * ($count - 1);
        $low  = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) return $sortedValues[$low];

        $fraction = $rank - $low;
        return $sortedValues[$low] + $fraction * ($sortedValues[$high] - $sortedValues[$low]);
    }

    private function stdDev(array $values): float
    {
        $count = count($values);
        if ($count < 2) return 0;
        $mean     = array_sum($values) / $count;
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / ($count - 1);
        return sqrt($variance);
    }

    /**
     * Pearson correlation coefficient.
     * Returns null if fewer than 3 data points (not statistically meaningful).
     */
    private function pearsonCorrelation(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3 || count($y) !== $n) return null;

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        $num   = 0; $denX = 0; $denY = 0;

        for ($i = 0; $i < $n; $i++) {
            $dx    = $x[$i] - $meanX;
            $dy    = $y[$i] - $meanY;
            $num  += $dx * $dy;
            $denX += $dx * $dx;
            $denY += $dy * $dy;
        }

        $den = sqrt($denX * $denY);
        return $den > 0 ? $num / $den : null;
    }


    // ─────────────────────────────────────────────────────────────────────────
    // DELETE UPLOAD
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteUpload(PortfolioCompany $company, $uploadId)
    {
        $upload = ExpenseUpload::where('portfolio_company_id', $company->id)->findOrFail($uploadId);
        ExpenseData::where('portfolio_company_id', $company->id)->where('upload_id', $uploadId)->delete();
        $upload->delete();
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUTO INSIGHTS
    // ─────────────────────────────────────────────────────────────────────────

    public function insights(\Illuminate\Http\Request $request, PortfolioCompany $company)
    {
        $from = $request->date_from;
        $to   = $request->date_to;
        $cid  = $company->id;
        return response()->json(['insights' => $this->buildInsights($cid, $from, $to)]);
    }

    private function buildInsights($cid, $from, $to): array
    {
        $insights = [];

        $totalExpense = (float) ExpenseData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('expense_amount');
        $totalRevenue = (float) \App\Models\SalesData::where('portfolio_company_id', $cid)->whereBetween('date', [$from, $to])->sum('net_sales_value');

        // ── Monthly trend ──
        $monthlyRows = ExpenseData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, DATE_FORMAT(`date`,'%Y%m')+0 as sort_key, SUM(expense_amount) as value")
            ->groupBy('period', 'sort_key')->orderBy('sort_key')->get();

        if ($monthlyRows->count() >= 2) {
            $values     = $monthlyRows->pluck('value')->map(fn($v) => (float)$v);
            $last       = $values->last();
            $prev       = $values->slice(-2, 1)->first();
            $mom        = $prev > 0 ? round(($last - $prev) / $prev * 100, 1) : 0;
            $lastPeriod = $monthlyRows->last()->period;

            if ($mom >= 20) {
                $insights[] = ['type' => 'danger', 'icon' => '🚨', 'title' => 'Sharp Expense Spike', 'body' => "{$lastPeriod} expenses jumped {$mom}% vs previous month — investigate immediately."];
            } elseif ($mom >= 10) {
                $insights[] = ['type' => 'warning', 'icon' => '⚠️', 'title' => 'Rising Expenses', 'body' => "{$lastPeriod} expenses rose {$mom}% month-over-month — monitor closely."];
            } elseif ($mom <= -10) {
                $insights[] = ['type' => 'positive', 'icon' => '✅', 'title' => 'Expense Reduction', 'body' => "{$lastPeriod} expenses dropped {$mom}% vs previous month — cost control working."];
            }

            // Volatility
            $max    = $values->max();
            $min    = $values->min();
            $spread = $max > 0 ? round(($max - $min) / $max * 100, 1) : 0;
            if ($spread > 50) {
                $bestPeriod = $monthlyRows->firstWhere('value', $min)?->period;
                $insights[] = ['type' => 'warning', 'icon' => '📊', 'title' => 'High Expense Volatility', 'body' => "Expenses fluctuated {$spread}% between highest and lowest months. Lowest month: {$bestPeriod}. Consider smoothing fixed cost structure."];
            }
        }

        // ── Expense-to-Revenue ratio ──
        if ($totalRevenue > 0) {
            $ratio = round($totalExpense / $totalRevenue * 100, 1);
            if ($ratio >= 90) {
                $insights[] = ['type' => 'danger', 'icon' => '🚨', 'title' => 'Critical Expense Ratio', 'body' => "Expenses are {$ratio}% of revenue — dangerously high. Profitability is at serious risk."];
            } elseif ($ratio >= 70) {
                $insights[] = ['type' => 'warning', 'icon' => '⚠️', 'title' => 'High Expense-to-Revenue Ratio', 'body' => "Expenses are {$ratio}% of revenue — limited room for profit. Target below 70%."];
            } elseif ($ratio <= 50) {
                $insights[] = ['type' => 'positive', 'icon' => '💚', 'title' => 'Healthy Expense Ratio', 'body' => "Expenses are only {$ratio}% of revenue — strong operational efficiency and healthy margins."];
            }
        }

        // ── Top category concentration ──
        $topCategory = ExpenseData::where('portfolio_company_id', $cid)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('expense_category')
            ->selectRaw('expense_category, SUM(expense_amount) as total')
            ->groupBy('expense_category')->orderByDesc('total')->first();

        if ($topCategory && $totalExpense > 0) {
            $pct = round($topCategory->total / $totalExpense * 100, 1);
            if ($pct >= 50) {
                $insights[] = ['type' => 'danger', 'icon' => '⚡', 'title' => 'Expense Concentration Risk', 'body' => "{$topCategory->expense_category} accounts for {$pct}% of total expenses. Heavy concentration in one category increases risk."];
            } elseif ($pct >= 35) {
                $insights[] = ['type' => 'warning', 'icon' => '🎯', 'title' => 'Dominant Expense Category', 'body' => "{$topCategory->expense_category} represents {$pct}% of total spend. Consider reviewing for optimization opportunities."];
            }
        }

        // ── Fastest growing category (MoM last 2 months) ──
        if ($monthlyRows->count() >= 2) {
            $periods = $monthlyRows->pluck('period')->toArray();
            $last2   = array_slice($periods, -2);
            if (count($last2) === 2) {
                $catGrowth = ExpenseData::where('portfolio_company_id', $cid)
                    ->whereIn(\Illuminate\Support\Facades\DB::raw("DATE_FORMAT(`date`,'%Y-%b')"), $last2)
                    ->whereNotNull('expense_category')
                    ->selectRaw("expense_category, DATE_FORMAT(`date`,'%Y-%b') as period, SUM(expense_amount) as total")
                    ->groupBy('expense_category', 'period')->get()
                    ->groupBy('expense_category');

                $biggestGrowth = null;
                $biggestPct    = 0;
                foreach ($catGrowth as $cat => $rows) {
                    $prevVal = (float)($rows->firstWhere('period', $last2[0])?->total ?? 0);
                    $currVal = (float)($rows->firstWhere('period', $last2[1])?->total ?? 0);
                    if ($prevVal > 0) {
                        $gr = ($currVal - $prevVal) / $prevVal * 100;
                        if ($gr > $biggestPct) { $biggestPct = $gr; $biggestGrowth = $cat; }
                    }
                }
                if ($biggestGrowth && $biggestPct >= 25) {
                    $insights[] = ['type' => 'warning', 'icon' => '📈', 'title' => 'Fastest Growing Expense', 'body' => "{$biggestGrowth} grew " . round($biggestPct, 1) . "% last month — fastest growing expense category. Review for necessity."];
                }
            }
        }

        // ── Months where expenses exceeded revenue ──
        if ($totalRevenue > 0 && $monthlyRows->count() > 0) {
            $revByMonth = \App\Models\SalesData::where('portfolio_company_id', $cid)
                ->whereBetween('date', [$from, $to])
                ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, SUM(net_sales_value) as rev")
                ->groupBy('period')->pluck('rev', 'period');

            $lossMonths = [];
            foreach ($monthlyRows as $row) {
                $rev = (float)($revByMonth[$row->period] ?? 0);
                if ($rev > 0 && (float)$row->value > $rev) {
                    $lossMonths[] = $row->period;
                }
            }
            if (count($lossMonths) > 0) {
                $insights[] = ['type' => 'danger', 'icon' => '🔴', 'title' => 'Operating Losses Detected', 'body' => count($lossMonths) . " month(s) had expenses exceeding revenue: " . implode(', ', $lossMonths) . ". Immediate review required."];
            }
        }

        // ── Below average recent performance ──
        if ($monthlyRows->count() >= 3) {
            $values     = $monthlyRows->pluck('value')->map(fn($v) => (float)$v);
            $avg        = $values->average();
            $lastVal    = $values->last();
            $lastPeriod = $monthlyRows->last()->period;
            if ($lastVal > $avg * 1.3) {
                $insights[] = ['type' => 'warning', 'icon' => '📉', 'title' => 'Above-Average Recent Spending', 'body' => "{$lastPeriod} was " . round(($lastVal/$avg - 1)*100, 1) . "% above the period monthly average — review for unusual items."];
            } elseif ($lastVal < $avg * 0.75) {
                $insights[] = ['type' => 'positive', 'icon' => '⭐', 'title' => 'Below-Average Recent Spending', 'body' => "{$lastPeriod} was " . round((1 - $lastVal/$avg)*100, 1) . "% below the period monthly average — good cost discipline."];
            }
        }

        return $insights;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NOTES — Save / Get / Update / Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function saveNote(\Illuminate\Http\Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
            'note'      => ['required', 'string', 'max:50000'],
        ]);

        \Illuminate\Support\Facades\DB::table('expense_dashboard_notes')->insert([
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

    public function getNotes(\Illuminate\Http\Request $request, PortfolioCompany $company)
    {
        $notes = \Illuminate\Support\Facades\DB::table('expense_dashboard_notes')
            ->where('portfolio_company_id', $company->id)
            ->where('date_from', $request->date_from)
            ->where('date_to',   $request->date_to)
            ->join('users', 'users.id', '=', 'expense_dashboard_notes.created_by')
            ->select('expense_dashboard_notes.*', 'users.name as author')
            ->orderByDesc('expense_dashboard_notes.updated_at')->get();

        return response()->json(['notes' => $notes]);
    }

    public function updateNote(\Illuminate\Http\Request $request, PortfolioCompany $company, $noteId)
    {
        $request->validate(['note' => ['required', 'string', 'max:50000']]);
        \Illuminate\Support\Facades\DB::table('expense_dashboard_notes')
            ->where('id', $noteId)->where('portfolio_company_id', $company->id)->where('created_by', Auth::id())
            ->update(['note' => $request->note, 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function deleteNote(PortfolioCompany $company, $noteId)
    {
        \Illuminate\Support\Facades\DB::table('expense_dashboard_notes')
            ->where('id', $noteId)->where('portfolio_company_id', $company->id)->where('created_by', Auth::id())
            ->delete();
        return response()->json(['success' => true]);
    }
}