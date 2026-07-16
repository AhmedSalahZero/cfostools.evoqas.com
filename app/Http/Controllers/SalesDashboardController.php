<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SalesData;
use App\Models\SalesFieldMapping;
use App\Models\PortfolioCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesDashboardController extends Controller
{
    private function authorizeSalesCompany($companyId): PortfolioCompany
    {
        return $this->authorizeCompany((int) $companyId, 'sales_analysis');
    }

    // Same field definitions as SalesAnalysisController
    private const FIELDS = [
        'date'                        => 'Date',
        'branch'                      => 'Branch',
        'document_number'             => 'Document Number',
        'business_unit'               => 'Business Unit',
        'business_sector'             => 'Business Sector',
        'sales_channel'               => 'Sales Channel',
        'country'                     => 'Country',
        'document_type'               => 'Document Type',
        'sales_person'                => 'Sales Person',
        'service_provider_name'       => 'Service Provider Name',
        'service_provider_type'       => 'Service Provider Type',
        'service_provider_birth_year' => 'Service Provider Birth Year',
        'principle'                   => 'Principle',
        'product_category'            => 'Product Category',
        'product_sub_category'        => 'Product Sub Category',
        'product_item'                => 'Product Item',
        'measurement_unit'            => 'Measurement Unit',
        'price_per_unit'              => 'Price Per Unit',
        'customer_name'               => 'Customer Name',
        'zone'                        => 'Zone',
        'quantity'                    => 'Quantity',
        'sales_value'                 => 'Sales Value',
        'cash_discount'               => 'Cash Discount',
        'quantity_discount'           => 'Quantity Discount',
        'special_discount'            => 'Special Discount',
        'other_discounts'             => 'Other Discounts',
        'net_sales_value'             => 'Net Sales Value',
    ];

    // Fields that are NOT usable as dimensions
    private const NON_DIMENSION_FIELDS = [
        'quantity', 'sales_value', 'cash_discount', 'quantity_discount',
        'special_discount', 'other_discounts', 'net_sales_value',
        'price_per_unit', 'service_provider_birth_year', 'document_number',
        'date', 'measurement_unit', 'document_type',
    ];

    // Fields to use for Top Achievers
    private const ACHIEVER_FIELDS = [
        'zone', 'sales_channel', 'branch', 'product_category',
        'product_item', 'business_sector', 'business_unit',
        'sales_person', 'principle', 'customer_name',
    ];

    // ── Helpers ──────────────────────────────────────────────

    private function getActiveFields($companyId): array
    {
        $active = SalesFieldMapping::where('portfolio_company_id', $companyId)
            ->where('is_active', true)->orderBy('sort_order')
            ->pluck('field_key')->toArray();
        return empty($active) ? array_keys(self::FIELDS) : $active;
    }

    private function getActiveDimensionFields($companyId): array
    {
        $active = $this->getActiveFields($companyId);
        return array_values(array_filter($active, fn($k) => !in_array($k, self::NON_DIMENSION_FIELDS)));
    }

    // ── Dashboard Page ────────────────────────────────────────

    public function dashboardPage($companyId)
    {
        $company  = $this->authorizeSalesCompany($companyId);
        $hasData  = SalesData::where('portfolio_company_id', $companyId)->exists();

        if (!$hasData) {
            // No data → redirect to upload page
            return redirect()->route('sales.field-mapping', $companyId);
        }

        $dimensionKeys = $this->getActiveDimensionFields($companyId);
        $dimensionFields = array_intersect_key(self::FIELDS, array_flip($dimensionKeys));

        // Default date: min date → max date of uploaded data for this company
        $dateRange = SalesData::where('portfolio_company_id', $companyId)
            ->selectRaw('MIN(`date`) as min_date, MAX(`date`) as max_date')
            ->first();

        $dateFrom = $dateRange?->max_date
            ? Carbon::parse($dateRange->max_date)->startOfYear()->format('Y-m-d')
            : Carbon::now()->startOfYear()->format('Y-m-d');

        $dateTo = $dateRange?->max_date
            ? Carbon::parse($dateRange->max_date)->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');

        return Inertia::render('Sales/Dashboard', [
            'company'         => ['id' => $company->id, 'name' => $company->name],
            'defaultDateFrom' => $dateFrom,
            'defaultDateTo'   => $dateTo,
            'dimensionFields' => $dimensionFields,
        ]);
    }

    // ── Dashboard Data API ─────────────────────────────────────

    public function dashboardData(Request $request, $companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
        ]);

        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        return response()->json([
            'kpis'           => $this->buildKpis($companyId, $dateFrom, $dateTo),
            'monthly_trend'  => $this->buildMonthlyTrend($companyId, $dateFrom, $dateTo),
            'breakdowns'     => $this->buildBreakdowns($companyId, $dateFrom, $dateTo),
            'top_achievers'  => $this->buildTopAchievers($companyId, $dateFrom, $dateTo),
            'customer_nature'=> $this->buildCustomerNature($companyId, $dateFrom, $dateTo),
            'insights'       => $this->buildInsights($companyId, $dateFrom, $dateTo),
        ]);
    }

    // ── KPI Cards ─────────────────────────────────────────────
    // Everything is driven by the user-selected date_from / date_to

    private function buildKpis($companyId, $dateFrom, $dateTo): array
    {
        $from = Carbon::parse($dateFrom);
        $to   = Carbon::parse($dateTo);

        // ── Base query for the selected period ──
        $periodQuery = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        $totalSales    = (clone $periodQuery)->sum('net_sales_value');
        $totalInvoices = (clone $periodQuery)->distinct('document_number')->count('document_number');
        $avgTransaction = $totalInvoices > 0 ? $totalSales / $totalInvoices : 0;

        // ── Last month WITHIN the selected range ──
        // = the most recent full calendar month inside date_from → date_to
        $lastMonthInRange      = $to->copy()->startOfMonth();
        $prevMonthInRange      = $lastMonthInRange->copy()->subMonth();

        $currentMonthSales = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [
                $lastMonthInRange->format('Y-m-d'),
                $to->format('Y-m-d'),
            ])->sum('net_sales_value');

        $lastMonthSales = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [
                $prevMonthInRange->format('Y-m-d'),
                $prevMonthInRange->copy()->endOfMonth()->format('Y-m-d'),
            ])->sum('net_sales_value');

        $currentMonthGr = $lastMonthSales > 0
            ? round(($currentMonthSales - $lastMonthSales) / $lastMonthSales * 100, 1) : 0;

        $currentMonthLabel = $lastMonthInRange->format('M Y');

        // ── Last 3 months WITHIN the selected range ──
        $last3To   = $to->copy()->endOfMonth();
        $last3From = $to->copy()->subMonths(2)->startOfMonth();
        // clamp to date_from
        if ($last3From->lt($from)) $last3From = $from->copy();

        $prior3To   = $last3From->copy()->subDay()->endOfMonth();
        $prior3From = $prior3To->copy()->subMonths(2)->startOfMonth();
        if ($prior3From->lt($from)) $prior3From = $from->copy();

        $last3Sales  = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$last3From->format('Y-m-d'), $last3To->format('Y-m-d')])
            ->sum('net_sales_value');

        $prior3Sales = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$prior3From->format('Y-m-d'), $prior3To->format('Y-m-d')])
            ->sum('net_sales_value');

        $last3Gr = $prior3Sales > 0
            ? round(($last3Sales - $prior3Sales) / $prior3Sales * 100, 1) : 0;

        // ── YTD: Jan 1 of the selected range's END year → date_to ──
        $ytdFrom = Carbon::create($to->year, 1, 1)->format('Y-m-d');
        $ytdTo   = $dateTo;
        // clamp to date_from if date_from is later than Jan 1
        if ($from->gt(Carbon::parse($ytdFrom))) $ytdFrom = $dateFrom;

        $ytd = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$ytdFrom, $ytdTo])
            ->sum('net_sales_value');

        // Months in YTD window
        $ytdMonths  = max(1, Carbon::parse($ytdFrom)->diffInMonths(Carbon::parse($ytdTo)) + 1);
        $avgMonthly = $ytd / $ytdMonths;

        // ── Active customers in selected period ──
        $activeCustomers = (clone $periodQuery)->whereNotNull('customer_name')
            ->distinct('customer_name')->count('customer_name');

        // Total unique customers ever for this company
        $totalCustomers = SalesData::where('portfolio_company_id', $companyId)
            ->whereNotNull('customer_name')->distinct('customer_name')->count('customer_name');

        // ── Best / worst month IN the selected range ──
        $monthlyRows = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, SUM(net_sales_value) as value")
            ->groupBy('period')->orderByDesc('value')->get();

        $bestMonth  = $monthlyRows->first();
        $worstMonth = $monthlyRows->last();

        return [
            // All values are within the user-selected date range
            'current_month'       => (float) $currentMonthSales,
            'current_month_gr'    => $currentMonthGr,
            'current_month_label' => $currentMonthLabel,           // e.g. "Feb 2025"
            'last_3_months'       => (float) $last3Sales,
            'last_3_months_gr'    => $last3Gr,
            'last_3_label'        => $last3From->format('M Y') . ' → ' . $last3To->format('M Y'),
            'ytd'                 => (float) $ytd,
            'ytd_label'           => Carbon::parse($ytdFrom)->format('M Y') . ' → ' . Carbon::parse($ytdTo)->format('M Y'),
            'avg_monthly'         => round($avgMonthly, 2),
            'avg_transaction'     => round($avgTransaction, 2),
            'total_invoices'      => $totalInvoices,
            'active_customers'    => $activeCustomers,
            'total_customers'     => $totalCustomers,
            'best_month_label'    => $bestMonth?->period,
            'best_month_value'    => (float) ($bestMonth?->value ?? 0),
            'worst_month_label'   => $worstMonth?->period,
            'worst_month_value'   => (float) ($worstMonth?->value ?? 0),
        ];
    }

    // ── Monthly Trend ─────────────────────────────────────────

    private function buildMonthlyTrend($companyId, $dateFrom, $dateTo): array
    {
        $rows = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, DATE_FORMAT(`date`,'%Y%m')+0 as sort_key, SUM(net_sales_value) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return $rows->map(fn($r) => [
            'period' => $r->period,
            'value'  => (float) $r->value,
        ])->values()->toArray();
    }

    // ── Sales Breakdowns ──────────────────────────────────────
    // One donut+table card per active dimension field

    private function buildBreakdowns($companyId, $dateFrom, $dateTo): array
    {
        $dimFields = $this->getActiveDimensionFields($companyId);

        // Exclude fields that are too granular or not useful for breakdown
        $excludeFromBreakdown = ['document_number', 'customer_name', 'product_item', 'product_sub_category', 'sales_person', 'service_provider_name'];
        $dimFields = array_filter($dimFields, fn($f) => !in_array($f, $excludeFromBreakdown));

        $breakdowns = [];
        foreach ($dimFields as $field) {
            $label = self::FIELDS[$field] ?? $field;

            $rows = SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull($field)->where($field, '!=', '')
                ->selectRaw("`$field` as label, SUM(net_sales_value) as value")
                ->groupBy($field)->orderByDesc('value')->limit(15)->get();

            $total = $rows->sum('value');
            $accum = 0;
            $rowsData = $rows->map(function($r) use ($total, &$accum) {
                $pct   = $total > 0 ? round($r->value / $total * 100, 1) : 0;
                $accum += $pct;
                return [
                    'label' => $r->label,
                    'value' => (float) $r->value,
                    'pct'   => $pct,
                    'accum' => round($accum, 1),
                ];
            })->values()->toArray();

            if (empty($rowsData)) continue;

            $breakdowns[] = [
                'field' => $field,
                'label' => $label,
                'rows'  => $rowsData,
            ];
        }

        return $breakdowns;
    }

    // ── Top Achievers ─────────────────────────────────────────

    private function buildTopAchievers($companyId, $dateFrom, $dateTo): array
    {
        $activeFields   = $this->getActiveFields($companyId);
        $achieverFields = array_intersect(self::ACHIEVER_FIELDS, $activeFields);

        $achievers = [];
        foreach ($achieverFields as $field) {
            $label = self::FIELDS[$field] ?? $field;

            $rows = SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull($field)->where($field, '!=', '')
                ->selectRaw("`$field` as label, SUM(net_sales_value) as value")
                ->groupBy($field)->orderByDesc('value')->get();

            if ($rows->isEmpty()) continue;

            $top   = $rows->first();
            $total = $rows->sum('value');

            $achievers[] = [
                'field'       => $field,
                'label'       => $label,
                'top_label'   => $top->label,
                'top_value'   => (float) $top->value,
                'total_items' => $rows->count(),
                'total_sales' => (float) $total,
            ];
        }

        return $achievers;
    }

    // ── Customer Nature ───────────────────────────────────────

    private function buildCustomerNature($companyId, $dateFrom, $dateTo): ?array
    {
        // Check if customer_name is an active field
        $activeFields = $this->getActiveFields($companyId);
        if (!in_array('customer_name', $activeFields)) return null;

        $currentYear = date('Y', strtotime($dateTo));
        $lastYear    = $currentYear - 1;
        $twoYearsAgo = $currentYear - 2;

        $thisYear = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear)->whereNotNull('customer_name')
            ->pluck('customer_name')->unique();

        $prevYear = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $lastYear)->whereNotNull('customer_name')
            ->pluck('customer_name')->unique();

        $twoYears = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $twoYearsAgo)->whereNotNull('customer_name')
            ->pluck('customer_name')->unique();

        $active = SalesData::where('portfolio_company_id', $companyId)
            ->whereNotNull('customer_name')
            ->selectRaw('customer_name, COUNT(DISTINCT YEAR(`date`)) as year_count')
            ->groupBy('customer_name')->having('year_count', '>=', 3)
            ->pluck('customer_name')->unique();

        $buckets = [
            'new'              => $thisYear->diff($prevYear)->diff($twoYears)->values(),
            'repeating'        => $thisYear->intersect($prevYear)->diff($twoYears)->values(),
            'active'           => $thisYear->intersect($active)->values(),
            'stop'             => $prevYear->diff($thisYear)->values(),
            'dead'             => $twoYears->diff($prevYear)->diff($thisYear)->values(),
            'stop_reactivated' => $thisYear->intersect($twoYears)->diff($prevYear)->values(),
        ];

        $salesByCustomer = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw('customer_name, SUM(net_sales_value) as total_sales')
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        $grandTotal = $salesByCustomer->sum('total_sales');

        $categories = collect($buckets)->map(function($customers, $key) use ($salesByCustomer, $grandTotal) {
            $rows = $customers->map(function($name) use ($salesByCustomer, $grandTotal) {
                $sales = (float)($salesByCustomer[$name]->total_sales ?? 0);
                return [
                    'name'       => $name,
                    'sales'      => $sales,
                    'percentage' => $grandTotal > 0 ? round($sales/$grandTotal*100, 2) : 0,
                ];
            })->sortByDesc('sales')->values();

            return [
                'label'       => $key,
                'count'       => $customers->count(),
                'total_sales' => $rows->sum('sales'),
                'customers'   => $rows->take(100)->values(),
            ];
        });

        return [
            'year'        => $currentYear,
            'grand_total' => (float) $grandTotal,
            'categories'  => $categories,
        ];
    }

    // ── Takeaway Popup Data ───────────────────────────────────

    public function takeaway(Request $request, $companyId)
    {
        $request->validate([
            'field'          => ['required', 'string'],
            'date_from'      => ['required', 'date'],
            'date_to'        => ['required', 'date'],
            'selected_value' => ['nullable', 'string'],
        ]);

        $field         = $request->field;
        $dateFrom      = $request->date_from;
        $dateTo        = $request->date_to;
        $selectedValue = $request->selected_value; // null = show top, string = drill into this item
        $activeFields  = $this->getActiveFields($companyId);

        // ── Full ranking of all values in this dimension (always shown) ──
        $rankingRows = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotNull($field)->where($field, '!=', '')
            ->selectRaw("`$field` as label, SUM(net_sales_value) as value")
            ->groupBy($field)->orderByDesc('value')->get();

        $totalAll = $rankingRows->sum('value');

        $ranking = $rankingRows->map(fn($r) => [
            'label' => $r->label,
            'value' => (float) $r->value,
            'pct'   => $totalAll > 0 ? round($r->value / $totalAll * 100, 1) : 0,
        ])->values()->toArray();

        // ── Stats: if selected_value given, filter to that item; else use top item ──
        $statLabel = $selectedValue ?? ($rankingRows->first()?->label ?? '');

        $statQuery = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where($field, $statLabel);

        $stats = [];

        // Net Sales of selected item
        $itemSales = (float)(clone $statQuery)->sum('net_sales_value');
        $stats[] = [
            'label' => 'Net Sales',
            'value' => number_format($itemSales, 0, '.', ','),
        ];

        // % of total
        $stats[] = [
            'label' => '% of Total Sales',
            'value' => $totalAll > 0 ? round($itemSales / $totalAll * 100, 1) . '%' : '—',
        ];

        // Unique customers
        if (in_array('customer_name', $activeFields)) {
            $stats[] = [
                'label' => 'Unique Customers',
                'value' => (clone $statQuery)->whereNotNull('customer_name')
                    ->distinct('customer_name')->count('customer_name'),
            ];
        }

        // Invoice count
        if (in_array('document_number', $activeFields)) {
            $stats[] = [
                'label' => 'Invoices',
                'value' => (clone $statQuery)->whereNotNull('document_number')
                    ->distinct('document_number')->count('document_number'),
            ];
        }

        // Product categories
        if (in_array('product_category', $activeFields) && $field !== 'product_category') {
            $stats[] = [
                'label' => 'Product Categories',
                'value' => (clone $statQuery)->whereNotNull('product_category')
                    ->distinct('product_category')->count('product_category'),
            ];
        }

        // Sales persons
        if (in_array('sales_person', $activeFields) && $field !== 'sales_person') {
            $stats[] = [
                'label' => 'Sales Reps',
                'value' => (clone $statQuery)->whereNotNull('sales_person')
                    ->distinct('sales_person')->count('sales_person'),
            ];
        }

        // Zones
        if (in_array('zone', $activeFields) && $field !== 'zone') {
            $stats[] = [
                'label' => 'Zones',
                'value' => (clone $statQuery)->whereNotNull('zone')
                    ->distinct('zone')->count('zone'),
            ];
        }

        // Branches
        if (in_array('branch', $activeFields) && $field !== 'branch') {
            $stats[] = [
                'label' => 'Branches',
                'value' => (clone $statQuery)->whereNotNull('branch')
                    ->distinct('branch')->count('branch'),
            ];
        }

        return response()->json([
            'ranking'        => $ranking,
            'stats'          => $stats,
            'selected_value' => $statLabel,
        ]);
    }

    // ── Auto Insights ─────────────────────────────────────────

    private function buildInsights($companyId, $dateFrom, $dateTo): array
    {
        $insights = [];

        // Monthly trend data
        $monthlyRows = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, DATE_FORMAT(`date`,'%Y%m')+0 as sort_key, SUM(net_sales_value) as value")
            ->groupBy('period', 'sort_key')->orderBy('sort_key')->get();

        if ($monthlyRows->count() >= 2) {
            $values = $monthlyRows->pluck('value')->map(fn($v) => (float)$v);
            $last   = $values->last();
            $prev   = $values->slice(-2, 1)->first();
            $mom    = $prev > 0 ? round(($last - $prev) / $prev * 100, 1) : 0;
            $lastPeriod = $monthlyRows->last()->period;

            if ($mom >= 20) {
                $insights[] = ['type' => 'positive', 'icon' => '🚀', 'title' => 'Strong Growth Month', 'body' => "$lastPeriod showed {$mom}% growth vs previous month — exceptional performance."];
            } elseif ($mom >= 5) {
                $insights[] = ['type' => 'positive', 'icon' => '📈', 'title' => 'Positive Momentum', 'body' => "$lastPeriod grew {$mom}% month-over-month — steady upward trend."];
            } elseif ($mom <= -20) {
                $insights[] = ['type' => 'danger', 'icon' => '🚨', 'title' => 'Sharp Sales Drop', 'body' => "$lastPeriod dropped {$mom}% vs previous month — requires immediate attention."];
            } elseif ($mom <= -5) {
                $insights[] = ['type' => 'warning', 'icon' => '⚠️', 'title' => 'Declining Trend', 'body' => "$lastPeriod declined {$mom}% month-over-month — monitor closely."];
            }

            // Best vs worst month spread
            $max = $values->max();
            $min = $values->min();
            if ($max > 0 && $min > 0) {
                $spread = round(($max - $min) / $max * 100, 1);
                if ($spread > 60) {
                    $bestPeriod = $monthlyRows->firstWhere('value', $max)?->period;
                    $insights[] = ['type' => 'warning', 'icon' => '📊', 'title' => 'High Sales Volatility', 'body' => "Sales fluctuated {$spread}% between best and worst months. Best month: $bestPeriod. Consider stabilizing revenue streams."];
                }
            }
        }

        // Customer nature insights
        if (in_array('customer_name', $this->getActiveFields($companyId))) {
            $totalCustomers = SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull('customer_name')->distinct('customer_name')->count('customer_name');

            $newCustomers = SalesData::where('portfolio_company_id', $companyId)
                ->whereYear('date', Carbon::parse($dateTo)->year)
                ->whereNotNull('customer_name')->distinct('customer_name')
                ->whereNotIn('customer_name', function($q) use ($companyId, $dateTo) {
                    $q->select('customer_name')->from('sales_data')
                      ->where('portfolio_company_id', $companyId)
                      ->whereYear('date', Carbon::parse($dateTo)->year - 1);
                })->count();

            if ($totalCustomers > 0) {
                $newPct = round($newCustomers / $totalCustomers * 100, 1);
                if ($newPct >= 30) {
                    $insights[] = ['type' => 'positive', 'icon' => '🌱', 'title' => 'Strong New Customer Acquisition', 'body' => "{$newPct}% of customers are new this year ({$newCustomers} customers) — healthy growth pipeline."];
                }
            }
        }

        // Concentration risk: if top dimension item > 50% of sales
        $activeFields = $this->getActiveDimensionFields($companyId);
        $checkFields  = array_intersect($activeFields, ['customer_name', 'principle', 'product_category']);
        foreach ($checkFields as $field) {
            $topRow = SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull($field)->where($field, '!=', '')
                ->selectRaw("`$field` as label, SUM(net_sales_value) as value")
                ->groupBy($field)->orderByDesc('value')->first();

            $totalSales = SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])->sum('net_sales_value');

            if ($topRow && $totalSales > 0) {
                $pct = round($topRow->value / $totalSales * 100, 1);
                $fieldLabel = self::FIELDS[$field] ?? $field;
                if ($pct >= 50) {
                    $insights[] = ['type' => 'danger', 'icon' => '⚡', 'title' => "High Concentration Risk — {$fieldLabel}", 'body' => "{$topRow->label} accounts for {$pct}% of total sales. Heavy dependence on a single {$fieldLabel} is a business risk."];
                } elseif ($pct >= 35) {
                    $insights[] = ['type' => 'warning', 'icon' => '🎯', 'title' => "Concentration Watch — {$fieldLabel}", 'body' => "{$topRow->label} represents {$pct}% of sales. Consider diversifying your {$fieldLabel} mix."];
                }
            }
        }

        // Total sales context
        $totalSales = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])->sum('net_sales_value');
        if ($totalSales > 0 && $monthlyRows->count() > 0) {
            $avgMonthly = $totalSales / $monthlyRows->count();
            $lastVal    = (float)($monthlyRows->last()?->value ?? 0);
            if ($lastVal > $avgMonthly * 1.3) {
                $insights[] = ['type' => 'positive', 'icon' => '⭐', 'title' => 'Above-Average Recent Performance', 'body' => 'Last month was ' . round(($lastVal/$avgMonthly - 1)*100, 1) . '% above the period monthly average — strong finish.'];
            } elseif ($lastVal < $avgMonthly * 0.7) {
                $insights[] = ['type' => 'warning', 'icon' => '📉', 'title' => 'Below-Average Recent Performance', 'body' => 'Last month was ' . round((1 - $lastVal/$avgMonthly)*100, 1) . '% below the period monthly average — needs attention.'];
            }
        }

        return $insights;
    }

    // ── Save Note ─────────────────────────────────────────────

    public function saveNote(Request $request, $companyId)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
            'note'      => ['required', 'string', 'max:50000'],
        ]);

        DB::table('sales_dashboard_notes')->updateOrInsert(
            [
                'portfolio_company_id' => $companyId,
                'date_from'            => $request->date_from,
                'date_to'              => $request->date_to,
                'created_by'           => Auth::id(),
            ],
            [
                'note'       => $request->note,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    // ── Get Notes ─────────────────────────────────────────────

    public function getNotes(Request $request, $companyId)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
        ]);

        $notes = DB::table('sales_dashboard_notes')
            ->where('portfolio_company_id', $companyId)
            ->where('date_from', $request->date_from)
            ->where('date_to', $request->date_to)
            ->join('users', 'users.id', '=', 'sales_dashboard_notes.created_by')
            ->select('sales_dashboard_notes.*', 'users.name as author')
            ->orderByDesc('sales_dashboard_notes.updated_at')
            ->get();

        return response()->json(['notes' => $notes]);
    }

    public function updateNote(Request $request, $companyId, $noteId)
    {
        $request->validate(['note' => ['required', 'string', 'max:50000']]);

        DB::table('sales_dashboard_notes')
            ->where('id', $noteId)
            ->where('portfolio_company_id', $companyId)
            ->where('created_by', Auth::id())
            ->update(['note' => $request->note, 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }

    // ── Delete Note ───────────────────────────────────────────

    public function deleteNote($companyId, $noteId)
    {
        DB::table('sales_dashboard_notes')
            ->where('id', $noteId)
            ->where('portfolio_company_id', $companyId)
            ->where('created_by', Auth::id())
            ->delete();

        return response()->json(['success' => true]);
    }
}