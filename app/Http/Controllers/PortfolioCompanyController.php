<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PortfolioCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Support\LeadSources;

class PortfolioCompanyController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            $query = PortfolioCompany::query();
            if ($request->has('organization')) {
                $query->where('organization_id', $request->organization);
            }
        } elseif ($user->hasRole('admin')) {
            $query = PortfolioCompany::where('organization_id', $user->organization_id);
        } else {
            $assignedIds = \App\Models\UserCompanyAssignment::where('user_id', $user->id)
                ->pluck('portfolio_company_id');
            $query = PortfolioCompany::whereIn('id', $assignedIds);
        }

        $companies = $query
            ->orderBy('name')
            ->get()
            ->map(function ($company) use ($user) {
                return [
                    'id'                    => $company->id,
                    'type'                  => $company->type,
                    'name'                  => $company->name,
                    'lead_source'           => $company->lead_source,
                    'sector'                => $company->sector,
                    'status'                => $company->status,
                    'transaction_date'      => $company->transaction_date?->format('Y-m-d'),
                    'invested_amount'       => (float) $company->invested_amount,
                    'invested_currency'     => $company->invested_currency,
                    'equity_stake'          => (float) $company->equity_stake,
                    'entry_valuation'       => (float) $company->entry_valuation,
                    'current_valuation'     => $company->current_valuation ? (float) $company->current_valuation : null,
                    'moic'                  => $company->moic ? (float) $company->moic : null,
                    'irr'                   => $company->irr  ? (float) $company->irr  : null,
                    'last_financial_update' => $company->last_financial_update?->format('Y-m-d'),
                    'ebitda_multiplier'     => $company->ebitda_multiplier ? (float) $company->ebitda_multiplier : null,
                    'notes'                 => $company->notes,
                    'permissions'           => [
                        'contracts'  => $user->canAccessPortfolioCompany($company, 'contracts'),
                        'documents'  => $user->canAccessPortfolioCompany($company, 'documents'),
                        'projects'   => $user->canAccessPortfolioCompany($company, 'projects'),
                        'surveys'    => $user->canAccessPortfolioCompany($company, 'surveys'),
                        'statistica' => $user->hasRole('super-admin')
                            || ($user->hasRole('admin') && (int) $user->organization_id === (int) $company->organization_id)
                            || $user->hasCompanyPermission($company->id, 'statistica'),
                    ],
                ];
            });

        
       // Resolve the correct org to show — super-admin can switch via ?organization=X
                        $viewOrgId = null;
                        if ($user->hasRole('super-admin')) {
                            $viewOrgId = $request->has('organization')
                                ? (int) $request->organization
                                : \App\Models\Organization::orderBy('id')->value('id'); // fallback: first org
                        } else {
                            $viewOrgId = (int) $user->organization_id;
                        }

                        return Inertia::render('PortfolioCompanies/Index', [
                            'companies' => $companies,
                            'orgId'     => $viewOrgId,
                        ]);
    }

    public function create()
    {
        $organizations = \App\Models\Organization::orderBy('name')->get(['id', 'name']);

        $orgId = Auth::user()->hasRole('super-admin')
            ? null
            : Auth::user()->organization_id;

        return Inertia::render('PortfolioCompanies/Create', [
            'organizations'     => $organizations,
            'defaultOrgId'      => Auth::user()->organization_id,
            'leadSourceOptions' => LeadSources::optionsForOrganization($orgId),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id'       => ['nullable', 'exists:organizations,id'],
            'type'                  => ['nullable', 'in:investment,prospect'],
            'name'                  => ['required', 'string', 'max:255'],
            'lead_source'           => ['required', 'string', 'max:100'],
            'sector'                => ['required', 'string', 'max:100'],
            'status'                => ['required', 'in:on_track,at_risk,watch'],
            'notes'                 => ['nullable', 'string'],
        ]);

        PortfolioCompany::create([
            'organization_id'   => Auth::user()->hasRole('super-admin')
                ? $request->input('organization_id')
                : Auth::user()->organization_id,
            'type'              => 'investment',
            'name'              => $validated['name'],
            'lead_source'       => $validated['lead_source'],
            'sector'            => $validated['sector'],
            'status'            => $validated['status'],
            'transaction_date'  => now()->toDateString(),
            'invested_amount'   => 0,
            'invested_currency' => 'EGP',
            'fx_currency'       => 'USD',
            'fx_rate'           => 1,
            'equity_stake'      => 1,
            'entry_valuation'   => 0,
            'notes'             => $validated['notes'] ?? null,
        ]);

        return redirect()->route('portfolio-companies.index')
            ->with('success', 'Customer added successfully!');
    }

    public function show(PortfolioCompany $company)
    {
        $this->authorizeCompany($company, 'view_company');
        $company->load('organization');
        $cid = $company->id;

        // ═══════════════════════════════════════════════════════════
        // 1. FINANCIAL STATEMENTS — last 3 periods (Income Statement)
        // Uses section_key (not display_name) and re-computes derived totals
        // ═══════════════════════════════════════════════════════════
        $statements = DB::table('financial_statements')
            ->where('portfolio_company_id', $cid)
            ->orderByDesc('period_to')
            ->limit(3)
            ->get(['id', 'period_from', 'period_to', 'currency', 'status']);

        // Computed section formulas (mirrors FinancialStatementController)
        $computedFormulas = [
            'gross_profit' => [['key'=>'sales_revenue','sign'=>1],['key'=>'cogs','sign'=>-1]],
            'ebitda'       => [['key'=>'gross_profit','sign'=>1],['key'=>'marketing_expenses','sign'=>-1],['key'=>'ga_expenses','sign'=>-1]],
            'ebit'         => [['key'=>'ebitda','sign'=>1],['key'=>'depreciation','sign'=>-1]],
            'ebt'          => [['key'=>'ebit','sign'=>1],['key'=>'finance_income_expense','sign'=>1]],
            'net_profit'   => [['key'=>'ebt','sign'=>1],['key'=>'taxes','sign'=>-1]],
        ];

        $fsData = [];
        foreach ($statements as $stmt) {
            $sections = DB::table('fs_sections')
                ->where('financial_statement_id', $stmt->id)
                ->where('statement_type', 'income')
                ->get(['id', 'section_key', 'is_computed']);

            $sectionIds = $sections->pluck('id');
            $lineSums = $sectionIds->isEmpty()
                ? collect()
                : DB::table('fs_line_items')
                    ->whereIn('fs_section_id', $sectionIds)
                    ->groupBy('fs_section_id')
                    ->selectRaw('fs_section_id, SUM(amount) as total')
                    ->pluck('total', 'fs_section_id');

            $totals = [];
            foreach ($sections as $sec) {
                if (!$sec->is_computed) {
                    $totals[$sec->section_key] = (float) ($lineSums[$sec->id] ?? 0);
                }
            }

            // Step 2: compute derived sections (up to 10 passes for propagation)
            for ($pass = 0; $pass < 10; $pass++) {
                foreach ($computedFormulas as $key => $formula) {
                    if (isset($totals[$key])) continue;
                    $val = 0.0;
                    $canCompute = true;
                    foreach ($formula as $part) {
                        if (!array_key_exists($part['key'], $totals)) {
                            $canCompute = false;
                            break;
                        }
                        $val += $totals[$part['key']] * $part['sign'];
                    }
                    if ($canCompute) {
                        $totals[$key] = $val;
                    }
                }
            }

            $fsData[] = [
                'id'          => $stmt->id,
                'period_from' => $stmt->period_from,
                'period_to'   => $stmt->period_to,
                'currency'    => $stmt->currency,
                'status'      => $stmt->status,
                'revenue'     => $totals['sales_revenue'] ?? null,
                'gross_profit'=> $totals['gross_profit']  ?? null,
                'ebitda'      => $totals['ebitda']        ?? null,
                'net_profit'  => $totals['net_profit']    ?? null,
            ];
        }

        // Latest FS for EBITDA-based valuation calc
        $latestFs = $fsData[0] ?? null;
        $ebitdaValuation = null;
        if ($latestFs && !empty($latestFs['ebitda']) && $company->ebitda_multiplier) {
            $ebitdaValuation = round((float) $latestFs['ebitda'] * (float) $company->ebitda_multiplier);
        }

        // Compute EBITDA-based valuation (read-only — no side-effect on GET)
        if ($ebitdaValuation && $ebitdaValuation > 0) {
            $company->current_valuation = $ebitdaValuation;
        }

        // ═══════════════════════════════════════════════════════════
        // 2. KPIs — latest values for active KPIs
        // ═══════════════════════════════════════════════════════════
        $kpiRows = DB::table('kpi_trackings as kt')
            ->join('kpi_definitions as kd', 'kd.id', '=', 'kt.kpi_definition_id')
            ->where('kt.company_id', $cid)
            ->orderByDesc('kt.period_label')
            ->limit(50)
            ->get([
                'kd.name as kpi_name',
                'kd.unit',
                'kd.higher_is_better',
                'kt.actual as actual_value',
                'kt.target as target_value',
                'kt.period_label as period_date',
            ]);

        // Deduplicate — keep most recent per KPI, compute status from actual vs target
        $kpiLatest = [];
        foreach ($kpiRows as $row) {
            if (!isset($kpiLatest[$row->kpi_name])) {
                $status = 'no_data';
                if ($row->actual_value !== null && $row->target_value !== null && $row->target_value != 0) {
                    $ratio = $row->actual_value / $row->target_value;
                    if ($row->higher_is_better) {
                        $status = $ratio >= 1.0 ? 'on_track' : ($ratio >= 0.75 ? 'watch' : 'at_risk');
                    } else {
                        $status = $ratio <= 1.0 ? 'on_track' : ($ratio <= 1.25 ? 'watch' : 'at_risk');
                    }
                }
                $row->status = $status;
                $kpiLatest[$row->kpi_name] = $row;
            }
        }
        $kpiSummary = array_values(array_slice($kpiLatest, 0, 8));

        // ═══════════════════════════════════════════════════════════
        // 3. BUDGET / VARIANCE — 7 P&L lines YTD Budget vs Actual
        // ═══════════════════════════════════════════════════════════
        $latestBudget = DB::table('budget_statements')
            ->where('portfolio_company_id', $cid)
            ->orderByDesc('year')
            ->first(['id', 'name', 'year', 'status', 'currency']);

        $budgetSummary = null;
        if ($latestBudget) {
            // ── Helper: sum monthly JSON up to current month (YTD) ──
            $currentMonth = (int) date('n');   // 1–12
            $budgetYear   = (int) $latestBudget->year;
            $currentYear  = (int) date('Y');
            // If budget year is in the past, sum all 12 months; if current year, sum up to now
            $ytdMonths    = ($budgetYear < $currentYear) ? 12 : $currentMonth;

            $sumYtd = function (?string $json) use ($ytdMonths): float {
                if (!$json) return 0.0;
                $arr = json_decode($json, true) ?? [];
                $total = 0.0;
                for ($m = 1; $m <= $ytdMonths; $m++) {
                    $total += (float) ($arr[$m] ?? $arr[(string)$m] ?? 0);
                }
                return $total;
            };

            // ── Load all income sections for this budget (with section_key) ──
            $incomeSections = DB::table('budget_sections')
                ->where('budget_statement_id', $latestBudget->id)
                ->where('statement_type', 'income')
                ->get(['id', 'section_key', 'is_computed']);

            $nonComputedSections = $incomeSections->where('is_computed', false);
            $sectionIds = $nonComputedSections->pluck('id');

            $lineItemsBySection = $sectionIds->isEmpty()
                ? collect()
                : DB::table('budget_groups as bg')
                    ->join('budget_line_items as bli', 'bli.budget_group_id', '=', 'bg.id')
                    ->whereIn('bg.budget_section_id', $sectionIds)
                    ->get(['bg.budget_section_id as section_id', 'bli.id as line_item_id', 'bli.monthly_amounts'])
                    ->groupBy('section_id');

            $allLiIds = $lineItemsBySection->flatten(1)->pluck('line_item_id');
            $actualsByLi = $allLiIds->isEmpty()
                ? collect()
                : DB::table('budget_actuals')
                    ->whereIn('budget_line_item_id', $allLiIds)
                    ->get(['budget_line_item_id', 'monthly_actuals'])
                    ->keyBy('budget_line_item_id');

            $sectionBudget = [];
            $sectionActual = [];

            foreach ($nonComputedSections as $sec) {
                $items = $lineItemsBySection->get($sec->id, collect());
                $secBudgetTotal = 0.0;
                $secActualTotal = 0.0;
                foreach ($items as $li) {
                    $secBudgetTotal += $sumYtd($li->monthly_amounts);
                    $act = $actualsByLi->get($li->line_item_id);
                    if ($act) {
                        $secActualTotal += $sumYtd($act->monthly_actuals);
                    }
                }
                $sectionBudget[$sec->section_key] = $secBudgetTotal;
                $sectionActual[$sec->section_key] = $secActualTotal;
            }

            // ── Compute derived (computed) sections from their formulas ──
            // Mirrors BudgetController / Variance.vue logic
            $computedFormulas = [
                'gross_profit' => [['key'=>'sales_revenue','sign'=>1], ['key'=>'cogs','sign'=>-1]],
                'ebitda'       => [['key'=>'gross_profit','sign'=>1],  ['key'=>'marketing_expenses','sign'=>-1], ['key'=>'ga_expenses','sign'=>-1]],
                'ebit'         => [['key'=>'ebitda','sign'=>1],        ['key'=>'depreciation','sign'=>-1]],
                'ebt'          => [['key'=>'ebit','sign'=>1],          ['key'=>'finance_income_expense','sign'=>1]],
                'net_profit'   => [['key'=>'ebt','sign'=>1],           ['key'=>'taxes','sign'=>-1]],
            ];

            // Multi-pass to resolve dependencies
            for ($pass = 0; $pass < 5; $pass++) {
                foreach ($computedFormulas as $key => $formula) {
                    if (isset($sectionBudget[$key])) continue;
                    $bVal = 0.0; $aVal = 0.0; $ok = true;
                    foreach ($formula as $part) {
                        if (!array_key_exists($part['key'], $sectionBudget)) { $ok = false; break; }
                        $bVal += $sectionBudget[$part['key']] * $part['sign'];
                        $aVal += $sectionActual[$part['key']] * $part['sign'];
                    }
                    if ($ok) {
                        $sectionBudget[$key] = $bVal;
                        $sectionActual[$key] = $aVal;
                    }
                }
            }

            // ── Build the 7-row P&L variance table ──
            $plRows = [
                ['key' => 'sales_revenue',  'label' => 'Revenue',     'is_expense' => false],
                ['key' => 'cogs',            'label' => 'COGS',        'is_expense' => true],
                ['key' => 'gross_profit',    'label' => 'Gross Profit','is_expense' => false],
                ['key' => 'ebitda',          'label' => 'EBITDA',      'is_expense' => false],
                ['key' => 'ebit',            'label' => 'EBIT',        'is_expense' => false],
                ['key' => 'ebt',             'label' => 'EBT',         'is_expense' => false],
                ['key' => 'net_profit',      'label' => 'Net Profit',  'is_expense' => false],
            ];

            $plVariance = [];
            foreach ($plRows as $row) {
                $budget   = $sectionBudget[$row['key']] ?? null;
                $actual   = $sectionActual[$row['key']] ?? null;
                $variance = ($budget !== null && $actual !== null) ? $actual - $budget : null;
                // For expense lines: negative variance = good (under budget)
                // For income lines:  positive variance = good (above budget)
                $plVariance[] = [
                    'key'        => $row['key'],
                    'label'      => $row['label'],
                    'is_expense' => $row['is_expense'],
                    'budget'     => $budget,
                    'actual'     => $actual,
                    'variance'   => $variance,
                    'var_pct'    => ($budget && $budget != 0)
                                    ? round(($variance / abs($budget)) * 100, 1)
                                    : null,
                ];
            }

            // ── Total budget for the old budgetPct computed prop ──
            $totalBudget = $sectionBudget['sales_revenue'] ?? 0;
            $totalActual = $sectionActual['sales_revenue'] ?? 0;

            $budgetSummary = [
                'id'           => $latestBudget->id,
                'name'         => $latestBudget->name,
                'year'         => $latestBudget->year,
                'status'       => $latestBudget->status,
                'currency'     => $latestBudget->currency,
                'ytd_months'   => $ytdMonths,
                'total_budget' => (float) $totalBudget,
                'total_actual' => (float) $totalActual,
                'variance'     => (float) $totalActual - (float) $totalBudget,
                'pl_variance'  => $plVariance,
            ];
        }

        // ═══════════════════════════════════════════════════════════
        // 4. CASH FORECAST — last 12 months of net cash position
        // ═══════════════════════════════════════════════════════════
        // cash_forecast_entries uses: month (Y-m), type (in/out), amount, portfolio_company_id
        $cashEntries = DB::table('cash_forecast_entries')
            ->where('portfolio_company_id', $cid)
            ->orderBy('month')
            ->get(['month', 'amount', 'type']);

        // Group by month — sum inflows minus outflows per month
        $cashByMonth = [];
        foreach ($cashEntries as $entry) {
            $m = $entry->month; // already Y-m format
            if (!isset($cashByMonth[$m])) {
                $cashByMonth[$m] = ['in' => 0, 'out' => 0];
            }
            if ($entry->type === 'in') {
                $cashByMonth[$m]['in'] += (float) $entry->amount;
            } else {
                $cashByMonth[$m]['out'] += (float) $entry->amount;
            }
        }
        $cashForecast = [];
        foreach ($cashByMonth as $month => $flows) {
            $cashForecast[] = [
                'month' => $month,
                'net'   => $flows['in'] - $flows['out'],
                'in'    => $flows['in'],
                'out'   => $flows['out'],
            ];
        }

        // ═══════════════════════════════════════════════════════════
        // 5. SALES — Sales Update KPIs + Top Achievers
        // ═══════════════════════════════════════════════════════════
        $salesSummary    = null;
        $salesKpis       = null;
        $salesTopAchievers = [];

        $salesDateRange = DB::table('sales_data')
            ->where('portfolio_company_id', $cid)
            ->selectRaw('MIN(`date`) as min_date, MAX(`date`) as max_date')
            ->first();

        if ($salesDateRange && $salesDateRange->max_date) {
            $maxDate   = $salesDateRange->max_date;
            $maxYear   = date('Y', strtotime($maxDate));
            $yearFrom  = $maxYear . '-01-01';
            $yearTo    = $maxDate;

            // ── Base totals ──
            $row = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->selectRaw('
                    SUM(net_sales_value) as total_revenue,
                    COUNT(DISTINCT product_item) as product_count,
                    COUNT(DISTINCT customer_name) as client_count,
                    COUNT(DISTINCT document_number) as total_invoices,
                    COUNT(*) as transaction_count
                ')
                ->first();

            // ── Last month in range ──
            $lastMonthEnd   = date('Y-m-t', strtotime($maxDate));
            $lastMonthStart = date('Y-m-01', strtotime($maxDate));
            $currentMonth = (float) DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$lastMonthStart, $lastMonthEnd])
                ->sum('net_sales_value');
            $currentMonthLabel = date('M Y', strtotime($maxDate));

            // Prior month for growth rate
            $prevMonthEnd   = date('Y-m-t', strtotime($lastMonthStart . ' -1 day'));
            $prevMonthStart = date('Y-m-01', strtotime($prevMonthEnd));
            $prevMonth = (float) DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$prevMonthStart, $prevMonthEnd])
                ->sum('net_sales_value');
            $currentMonthGr = $prevMonth > 0 ? round(($currentMonth - $prevMonth) / $prevMonth * 100, 1) : 0;

            // ── Last 3 months ──
            $last3End   = $lastMonthEnd;
            $last3Start = date('Y-m-01', strtotime($lastMonthStart . ' -2 months'));
            $last3 = (float) DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$last3Start, $last3End])
                ->sum('net_sales_value');
            $prior3End   = date('Y-m-t', strtotime($last3Start . ' -1 day'));
            $prior3Start = date('Y-m-01', strtotime($prior3End . ' -2 months'));
            $prior3 = (float) DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$prior3Start, $prior3End])
                ->sum('net_sales_value');
            $last3Gr = $prior3 > 0 ? round(($last3 - $prior3) / $prior3 * 100, 1) : 0;

            // ── Best/Worst month in year ──
            $monthlyRows = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, DATE_FORMAT(`date`,'%Y%m')+0 as sort_key, SUM(net_sales_value) as value")
                ->groupBy('period', 'sort_key')
                ->orderBy('sort_key')
                ->get();
            $bestMonth  = $monthlyRows->sortByDesc('value')->first();
            $worstMonth = $monthlyRows->sortBy('value')->first();
            $avgMonthly = $monthlyRows->count() > 0 ? round($monthlyRows->avg('value'), 0) : 0;
            $totalInvoices = (int) ($row->total_invoices ?? 0);
            $avgTransaction = $totalInvoices > 0 ? round((float)($row->total_revenue ?? 0) / $totalInvoices, 0) : 0;

            $salesKpis = [
                'ytd'                  => (float) ($row->total_revenue ?? 0),
                'ytd_label'            => 'Jan – ' . date('M Y', strtotime($maxDate)),
                'avg_monthly'          => $avgMonthly,
                'current_month'        => $currentMonth,
                'current_month_label'  => $currentMonthLabel,
                'current_month_gr'     => $currentMonthGr,
                'last_3_months'        => $last3,
                'last_3_label'         => date('M Y', strtotime($last3Start)) . ' – ' . date('M Y', strtotime($last3End)),
                'last_3_months_gr'     => $last3Gr,
                'avg_transaction'      => $avgTransaction,
                'total_invoices'       => $totalInvoices,
                'active_customers'     => (int) ($row->client_count ?? 0),
                'best_month_label'     => $bestMonth?->period,
                'best_month_value'     => $bestMonth ? (float) $bestMonth->value : 0,
                'worst_month_label'    => $worstMonth?->period,
            ];

            $salesSummary = [
                'period'            => 'YTD ' . $maxYear,
                'total_revenue'     => (float) ($row->total_revenue ?? 0),
                'product_count'     => (int) ($row->product_count ?? 0),
                'client_count'      => (int) ($row->client_count ?? 0),
                'transaction_count' => (int) ($row->transaction_count ?? 0),
            ];

            // ── Top Achievers (top product, top customer, top category) ──
            $topProduct = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->whereNotNull('product_item')
                ->selectRaw('product_item as label, SUM(net_sales_value) as value, COUNT(DISTINCT product_item) as total_items')
                ->groupBy('product_item')
                ->orderByDesc('value')
                ->first();

            $topCustomer = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->whereNotNull('customer_name')
                ->selectRaw('customer_name as label, SUM(net_sales_value) as value')
                ->groupBy('customer_name')
                ->orderByDesc('value')
                ->first();

            $totalCustomers = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->distinct('customer_name')->count('customer_name');

            $topCategory = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->whereNotNull('product_category')
                ->selectRaw('product_category as label, SUM(net_sales_value) as value')
                ->groupBy('product_category')
                ->orderByDesc('value')
                ->first();

            $totalProducts = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->distinct('product_item')->count('product_item');

            $totalCategories = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$yearFrom, $yearTo])
                ->distinct('product_category')->count('product_category');

            if ($topProduct) {
                $salesTopAchievers[] = [
                    'field'       => 'product_item',
                    'label'       => 'Product',
                    'top_label'   => $topProduct->label,
                    'top_value'   => (float) $topProduct->value,
                    'total_items' => $totalProducts,
                ];
            }
            if ($topCustomer) {
                $salesTopAchievers[] = [
                    'field'       => 'customer_name',
                    'label'       => 'Customer',
                    'top_label'   => $topCustomer->label,
                    'top_value'   => (float) $topCustomer->value,
                    'total_items' => $totalCustomers,
                ];
            }
            if ($topCategory) {
                $salesTopAchievers[] = [
                    'field'       => 'product_category',
                    'label'       => 'Category',
                    'top_label'   => $topCategory->label,
                    'top_value'   => (float) $topCategory->value,
                    'total_items' => $totalCategories,
                ];
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 6. EXPENSES — 6 KPI cards + Top 10 items (latest year)
        // ═══════════════════════════════════════════════════════════
        $expenseSummary  = null;   // kept for backward compat (expenseRatioPct in template)
        $expenseKpis     = null;
        $expenseTopItems = [];

        $expenseDateRange = DB::table('expense_data')
            ->where('portfolio_company_id', $cid)
            ->selectRaw('MIN(`date`) as min_date, MAX(`date`) as max_date')
            ->first();

        if ($expenseDateRange && $expenseDateRange->max_date) {
            $expMaxYear  = date('Y', strtotime($expenseDateRange->max_date));
            $expYearFrom = $expMaxYear . '-01-01';
            $expYearTo   = $expenseDateRange->max_date;

            // ── Totals row ──
            $eRow = DB::table('expense_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$expYearFrom, $expYearTo])
                ->selectRaw('
                    SUM(expense_amount)                      as total_expenses,
                    COUNT(DISTINCT expense_category)         as category_count,
                    COUNT(DISTINCT expense_name)             as item_count
                ')
                ->first();

            $totalExpense  = (float) ($eRow->total_expenses ?? 0);
            $categoryCount = (int)   ($eRow->category_count ?? 0);
            $itemCount     = (int)   ($eRow->item_count     ?? 0);

            // ── Revenue for the same window (from sales_data) ──
            $totalRevenue = (float) DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$expYearFrom, $expYearTo])
                ->sum('net_sales_value');

            // ── Monthly average ──
            $monthCount = DB::table('expense_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$expYearFrom, $expYearTo])
                ->selectRaw("COUNT(DISTINCT DATE_FORMAT(`date`,'%Y-%m')) as cnt")
                ->value('cnt');
            $avgMonthly = $monthCount > 0 ? round($totalExpense / $monthCount, 2) : 0;

            // ── 6 KPI cards payload ──
            $expenseKpis = [
                'total_expense'  => $totalExpense,
                'total_revenue'  => $totalRevenue,
                'expense_to_rev' => $totalRevenue > 0 ? round($totalExpense / $totalRevenue * 100, 1) : 0,
                'category_count' => $categoryCount,
                'item_count'     => $itemCount,
                'avg_monthly'    => $avgMonthly,
                'period_label'   => 'YTD ' . $expMaxYear,
            ];

            // ── Top 10 expense items ──
            $expenseTopItems = DB::table('expense_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$expYearFrom, $expYearTo])
                ->whereNotNull('expense_name')
                ->selectRaw('expense_category, expense_name, SUM(expense_amount) as total')
                ->groupBy('expense_category', 'expense_name')
                ->orderByRaw('SUM(expense_amount) DESC')
                ->limit(10)
                ->get()
                ->map(fn($r) => [
                    'category' => $r->expense_category,
                    'item'     => $r->expense_name,
                    'total'    => (float) $r->total,
                    'pct'      => $totalExpense > 0 ? round($r->total / $totalExpense * 100, 1) : 0,
                ])
                ->toArray();

            // ── Keep expenseSummary for the expenseRatioPct computed in Vue ──
            $expenseSummary = [
                'period'         => 'YTD ' . $expMaxYear,
                'total_expenses' => $totalExpense,
                'category_count' => $categoryCount,
            ];
        }

        // ═══════════════════════════════════════════════════════════
        // 7. PROFITABILITY — margin data derived from sales + expense
        // ═══════════════════════════════════════════════════════════
        // Revenue from sales_data, COGS+OpEx mapped from expense_data via pl_mappings
        $profitSummary = null;

        // Reuse sales date range already fetched above
        if (!empty($salesDateRange) && $salesDateRange->max_date) {
            $profMaxYear  = date('Y', strtotime($salesDateRange->max_date));
            $profYearFrom = $profMaxYear . '-01-01';
            $profYearTo   = $salesDateRange->max_date;

            $profRevenue = DB::table('sales_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$profYearFrom, $profYearTo])
                ->sum('net_sales_value');

            // Get expense categories mapped to cogs/opex for this company
            // Column is pl_line (not mapped_to), keyed by expense_category
            $cogsCategories = DB::table('profitability_pl_mappings')
                ->where('portfolio_company_id', $cid)
                ->where('pl_line', 'cogs')
                ->pluck('expense_category');

            $opexCategories = DB::table('profitability_pl_mappings')
                ->where('portfolio_company_id', $cid)
                ->where('pl_line', 'opex')
                ->pluck('expense_category');

            $profCogs = DB::table('expense_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$profYearFrom, $profYearTo])
                ->whereIn('expense_category', $cogsCategories)
                ->sum('expense_amount');

            $profOpex = DB::table('expense_data')
                ->where('portfolio_company_id', $cid)
                ->whereBetween('date', [$profYearFrom, $profYearTo])
                ->whereIn('expense_category', $opexCategories)
                ->sum('expense_amount');

            // ── DA / Interest / Tax — from pl_mappings (expense_data) ──
            $daCategories       = DB::table('profitability_pl_mappings')
                ->where('portfolio_company_id', $cid)->where('pl_line', 'da')->pluck('expense_category');
            $interestCategories = DB::table('profitability_pl_mappings')
                ->where('portfolio_company_id', $cid)->where('pl_line', 'interest')->pluck('expense_category');
            $taxCategories      = DB::table('profitability_pl_mappings')
                ->where('portfolio_company_id', $cid)->where('pl_line', 'tax')->pluck('expense_category');

            $profDa       = (float) DB::table('expense_data')
                ->where('portfolio_company_id', $cid)->whereBetween('date', [$profYearFrom, $profYearTo])
                ->whereIn('expense_category', $daCategories)->sum('expense_amount');
            $profInterest = (float) DB::table('expense_data')
                ->where('portfolio_company_id', $cid)->whereBetween('date', [$profYearFrom, $profYearTo])
                ->whereIn('expense_category', $interestCategories)->sum('expense_amount');
            $profTax      = (float) DB::table('expense_data')
                ->where('portfolio_company_id', $cid)->whereBetween('date', [$profYearFrom, $profYearTo])
                ->whereIn('expense_category', $taxCategories)->sum('expense_amount');

            // ── Manual inputs (profitability_manual_inputs) — aggregate YTD ──
            $manualRows = DB::table('profitability_manual_inputs')
                ->where('portfolio_company_id', $cid)
                ->where('period_type', 'month')
                ->where('period_label', 'like', $profMaxYear . '-%')
                ->selectRaw('SUM(da_amount) as da, SUM(interest_amount) as interest, SUM(tax_amount) as tax')
                ->first();

            $profDa       += (float) ($manualRows->da       ?? 0);
            $profInterest += (float) ($manualRows->interest ?? 0);
            $profTax      += (float) ($manualRows->tax      ?? 0);

            $revenue     = (float) $profRevenue;
            $grossProfit = $revenue - (float) $profCogs;
            $ebitda      = $grossProfit - (float) $profOpex;
            $ebit        = $ebitda - $profDa;
            $ebt         = $ebit   - $profInterest;
            $netProfit   = $ebt    - $profTax;

            $pct = fn($v) => $revenue > 0 ? round($v / $revenue * 100, 1) : null;

            if ($revenue > 0) {
                $profitSummary = [
                    'period'         => 'YTD ' . $profMaxYear,
                    'revenue'        => $revenue,
                    'gross_profit'   => $grossProfit,
                    'ebitda'         => $ebitda,
                    'ebit'           => $ebit,
                    'ebt'            => $ebt,
                    'net_profit'     => $netProfit,
                    'gross_margin'   => $pct($grossProfit),
                    'ebitda_margin'  => $pct($ebitda),
                    'ebit_margin'    => $pct($ebit),
                    'ebt_margin'     => $pct($ebt),
                    'net_margin'     => $pct($netProfit),
                ];
            }
        }

        return Inertia::render('PortfolioCompanies/Show', [
            'company'           => $company,
            'canEdit'           => auth()->user()->canEditPortfolioCompany($company),
            'fsData'            => $fsData,
            'ebitdaValuation'   => $ebitdaValuation,
            'kpiSummary'        => $kpiSummary,
            'budgetSummary'     => $budgetSummary,
            'cashForecast'      => $cashForecast,
            'salesSummary'      => $salesSummary,
            'salesKpis'         => $salesKpis,
            'salesTopAchievers' => $salesTopAchievers,
            'expenseSummary'    => $expenseSummary,
            'expenseKpis'       => $expenseKpis,
            'expenseTopItems'   => $expenseTopItems,
            'profitSummary'     => $profitSummary,
        ]);
    }

    public function edit(PortfolioCompany $company)
    {
        abort_unless(auth()->user()->canEditPortfolioCompany($company), 403);

        return inertia('PortfolioCompanies/Edit', [
            'company' => [
                'id'                    => $company->id,
                'type'                  => $company->type,
                'name'                  => $company->name,
                'lead_source'           => $company->lead_source,
                'sector'                => $company->sector,
                'status'                => $company->status,
                'transaction_date'      => $company->transaction_date?->format('Y-m-d'),
                'last_financial_update' => $company->last_financial_update?->format('Y-m-d'),
                'invested_amount'       => (float) $company->invested_amount,
                'invested_currency'     => $company->invested_currency,
                'fx_currency'           => $company->fx_currency,
                'fx_rate'               => (float) $company->fx_rate,
                'equity_stake'          => (float) $company->equity_stake,
                'ebitda_multiplier'     => $company->ebitda_multiplier ? (float) $company->ebitda_multiplier : null,
                'entry_valuation'       => (float) $company->entry_valuation,
                'current_valuation'     => $company->current_valuation ? (float) $company->current_valuation : null,
                'moic'                  => $company->moic ? (float) $company->moic : null,
                'irr'                   => $company->irr  ? (float) $company->irr  : null,
                'notes'                 => $company->notes,
            ],
            'leadSourceOptions' => LeadSources::optionsForOrganization($company->organization_id),
        ]);
    }

    public function update(Request $request, PortfolioCompany $company)
    {
        abort_unless(auth()->user()->canEditPortfolioCompany($company), 403);

        $validated = $request->validate([
            'type'                  => ['nullable', 'in:investment,prospect'],
            'name'                  => 'required|string|max:255',
            'lead_source'           => ['required', 'string', 'max:100'],
            'sector'                => 'required|string|max:100',
            'status'                => 'required|in:on_track,at_risk,watch',
            'notes'                 => 'nullable|string',
        ]);

        $company->update([
            'type'        => 'investment',
            'name'        => $validated['name'],
            'lead_source' => $validated['lead_source'],
            'sector'      => $validated['sector'],
            'status'      => $validated['status'],
            'notes'       => $validated['notes'] ?? null,
        ]);

        return redirect()->route('portfolio-companies.index')
            ->with('flash', ['success' => $company->name . ' has been updated successfully.']);
    }

    public function destroy(PortfolioCompany $company)
    {
        $this->authorizeCompanyManage($company);

        $companyName = $company->name;

        DB::transaction(function () use ($company) {
        // ── 1. Sales data ──────────────────────────────────────────────────
        $salesUploadIds = \App\Models\SalesUpload::where('portfolio_company_id', $company->id)->pluck('id');
        \App\Models\SalesData::whereIn('upload_id', $salesUploadIds)->delete();
        \App\Models\SalesUpload::whereIn('id', $salesUploadIds)->delete();
        DB::table('sales_field_mappings')->where('portfolio_company_id', $company->id)->delete();
        DB::table('sales_reports')->where('portfolio_company_id', $company->id)->delete();
        DB::table('sales_dashboard_notes')->where('portfolio_company_id', $company->id)->delete();

        // ── 2. Expense data ────────────────────────────────────────────────
        $expenseUploadIds = \App\Models\ExpenseUpload::where('portfolio_company_id', $company->id)->pluck('id');
        \App\Models\ExpenseData::whereIn('upload_id', $expenseUploadIds)->delete();
        \App\Models\ExpenseUpload::whereIn('id', $expenseUploadIds)->delete();
        DB::table('expense_dashboard_notes')->where('portfolio_company_id', $company->id)->delete();

        // ── 3. Profitability ───────────────────────────────────────────────
        DB::table('profitability_pl_mappings')->where('portfolio_company_id', $company->id)->delete();
        DB::table('profitability_manual_inputs')->where('portfolio_company_id', $company->id)->delete();
        DB::table('profitability_dashboard_notes')->where('portfolio_company_id', $company->id)->delete();

        // ── 4. Financial Statements ────────────────────────────────────────
        $statementIds = \App\Models\FinancialStatement::where('portfolio_company_id', $company->id)->pluck('id');
        foreach ($statementIds as $stmtId) {
            $sectionIds = DB::table('fs_sections')->where('financial_statement_id', $stmtId)->pluck('id');
            DB::table('fs_line_items')->whereIn('fs_section_id', $sectionIds)->delete();
            DB::table('fs_sections')->where('financial_statement_id', $stmtId)->delete();
            DB::table('fs_ratios')->where('financial_statement_id', $stmtId)->delete();
        }
        \App\Models\FinancialStatement::whereIn('id', $statementIds)->delete();

        // ── 5. Financial Planning Models (delete files from disk too) ──────
        $planningModels = \App\Models\FinancialPlanningModel::where('portfolio_company_id', $company->id)->get();
        foreach ($planningModels as $model) {
            if ($model->file_path && Storage::disk('public')->exists($model->file_path)) {
                Storage::disk('public')->delete($model->file_path);
            }
            $model->delete();
        }

        // ── 6. Budgets ─────────────────────────────────────────────────────
        $budgetIds = DB::table('budget_statements')->where('portfolio_company_id', $company->id)->pluck('id');
        foreach ($budgetIds as $budgetId) {
            $sectionIds = DB::table('budget_sections')->where('budget_statement_id', $budgetId)->pluck('id');
            foreach ($sectionIds as $secId) {
                $groupIds = DB::table('budget_groups')->where('budget_section_id', $secId)->pluck('id');
                foreach ($groupIds as $grpId) {
                    $liIds = DB::table('budget_line_items')->where('budget_group_id', $grpId)->pluck('id');
                    DB::table('budget_actuals')->whereIn('budget_line_item_id', $liIds)->delete();
                    DB::table('budget_line_items')->whereIn('id', $liIds)->delete();
                }
                DB::table('budget_groups')->whereIn('id', $groupIds)->delete();
            }
            DB::table('budget_sections')->whereIn('id', $sectionIds)->delete();
        }
        DB::table('budget_statements')->whereIn('id', $budgetIds)->delete();

        // ── 7. Cash Forecast ───────────────────────────────────────────────
        DB::table('cash_forecast_entries')->where('portfolio_company_id', $company->id)->delete();

        // ── 8. KPIs ────────────────────────────────────────────────────────
        DB::table('kpi_trackings')->where('company_id', $company->id)->delete();

        // ── 9. Contracts & services ────────────────────────────────────────
        foreach ($company->contracts as $contract) {
            $contract->services()->delete();
            $contract->delete();
        }

        // ── 10. Delete the company itself ──────────────────────────────────
        $company->delete();
        });

        return redirect()->route('portfolio-companies.index')
            ->with('flash', ['success' => "{$companyName} and all its data have been permanently deleted."]);
    }
}