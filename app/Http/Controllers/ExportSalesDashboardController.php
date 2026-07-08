<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ExportSalesData;
use App\Models\ExportSalesFieldMapping;
use App\Models\PortfolioCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExportSalesDashboardController extends Controller
{
    private function authorizeExportSalesCompany($companyId): PortfolioCompany
    {
        return $this->authorizeCompany((int) $companyId, 'export_sales_analysis');
    }

    // All 38 export sales fields
    private const FIELDS = [
        'date'                        => 'Date',
        'purchase_order_number'       => 'PO Number',
        'purchase_order_date'         => 'PO Date',
        'purchase_order_value'        => 'PO Value',
        'purchase_order_net_value'    => 'PO Net Value',
        'purchase_order_status'       => 'PO Status',
        'business_unit'               => 'Business Unit',
        'revenue_stream'              => 'Revenue Stream',
        'customer_name'               => 'Customer Name',
        'consignee'                   => 'Consignee',
        'broker'                      => 'Broker',
        'export_bank'                 => 'Export Bank',
        'loading_country'             => 'Loading Country',
        'destination_country'         => 'Destination Country',
        'port_of_loading'             => 'Port of Loading',
        'port_of_destination'         => 'Port of Destination',
        'shipping_line'               => 'Shipping Line',
        'booking_number'              => 'Booking Number',
        'incoterms'                   => 'Incoterms',
        'payment_terms'               => 'Payment Terms',
        'product_category'            => 'Product Category',
        'product_item'                => 'Product Item',
        'origin'                      => 'Origin',
        'packing_unit_of_measurement' => 'Packing UOM',
        'packing_quantity'            => 'Packing Quantity',
        'packing_type'                => 'Packing Type',
        'full_container_load_count'   => 'FCL Count',
        'full_container_load_type'    => 'FCL Type',
        'quantity_unit_of_measurement'=> 'Quantity UOM',
        'quantity'                    => 'Quantity',
        'currency'                    => 'Currency',
        'price_per_unit'              => 'Price Per Unit',
        'freight_value'               => 'Freight Value',
        'cut_off_date'                => 'Cut-Off Date',
        'estimated_time_of_sailing'   => 'ETS',
        'estimated_time_of_arrival'   => 'ETA',
        'inspection_company'          => 'Inspection Company',
        'clearance_agent'             => 'Clearance Agent',
        'documents_sending_type'      => 'Documents Sending Type',
    ];

    // Fields that are NOT usable as dimensions
    private const NON_DIMENSION_FIELDS = [
        'quantity', 'purchase_order_value', 'purchase_order_net_value',
        'price_per_unit', 'freight_value', 'packing_quantity',
        'full_container_load_count', 'purchase_order_number', 'booking_number',
        'date', 'purchase_order_date', 'cut_off_date',
        'estimated_time_of_sailing', 'estimated_time_of_arrival',
        'quantity_unit_of_measurement', 'packing_unit_of_measurement',
    ];

    // Fields to use for Top Achievers
    private const ACHIEVER_FIELDS = [
        'destination_country', 'customer_name', 'product_category',
        'product_item', 'shipping_line', 'business_unit',
        'revenue_stream', 'incoterms', 'payment_terms', 'origin',
    ];

    // ── Helpers ──────────────────────────────────────────────

    private function getActiveFields($companyId): array
    {
        $active = ExportSalesFieldMapping::where('portfolio_company_id', $companyId)
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
        $company = $this->authorizeExportSalesCompany($companyId);
        $hasData = ExportSalesData::where('portfolio_company_id', $companyId)->exists();

        if (!$hasData) {
            return redirect()->route('export-sales.field-mapping', $companyId);
        }

        $dimensionKeys   = $this->getActiveDimensionFields($companyId);
        $dimensionFields = array_intersect_key(self::FIELDS, array_flip($dimensionKeys));

        $dateRange = ExportSalesData::where('portfolio_company_id', $companyId)
            ->selectRaw('MIN(`date`) as min_date, MAX(`date`) as max_date')
            ->first();

        $dateFrom = $dateRange?->max_date
            ? Carbon::parse($dateRange->max_date)->startOfYear()->format('Y-m-d')
            : Carbon::now()->startOfYear()->format('Y-m-d');

        $dateTo = $dateRange?->max_date
            ? Carbon::parse($dateRange->max_date)->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');

        return Inertia::render('ExportSales/Dashboard', [
            'company'         => ['id' => $company->id, 'name' => $company->name],
            'defaultDateFrom' => $dateFrom,
            'defaultDateTo'   => $dateTo,
            'dimensionFields' => $dimensionFields,
        ]);
    }

    // ── Dashboard Data API ─────────────────────────────────────

    public function dashboardData(Request $request, $companyId)
    {
        $this->authorizeExportSalesCompany($companyId);
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
        ]);

        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        return response()->json([
            'kpis'          => $this->buildKpis($companyId, $dateFrom, $dateTo),
            'monthly_trend' => $this->buildMonthlyTrend($companyId, $dateFrom, $dateTo),
            'breakdowns'    => $this->buildBreakdowns($companyId, $dateFrom, $dateTo),
            'top_achievers' => $this->buildTopAchievers($companyId, $dateFrom, $dateTo),
            'po_status'     => $this->buildPoStatus($companyId, $dateFrom, $dateTo),
            'insights'      => $this->buildInsights($companyId, $dateFrom, $dateTo),
        ]);
    }

    // ── KPI Cards ─────────────────────────────────────────────

    private function buildKpis($companyId, $dateFrom, $dateTo): array
    {
        $from = Carbon::parse($dateFrom);
        $to   = Carbon::parse($dateTo);

        $periodQuery = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        $totalValue  = (clone $periodQuery)->sum('purchase_order_net_value');
        $totalOrders = (clone $periodQuery)->distinct('purchase_order_number')->count('purchase_order_number');
        $avgOrderValue = $totalOrders > 0 ? $totalValue / $totalOrders : 0;

        // Last month in range
        $lastMonthStart = $to->copy()->startOfMonth();
        $prevMonthStart = $lastMonthStart->copy()->subMonth();

        $currentMonthValue = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$lastMonthStart->format('Y-m-d'), $to->format('Y-m-d')])
            ->sum('purchase_order_net_value');

        $lastMonthValue = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [
                $prevMonthStart->format('Y-m-d'),
                $prevMonthStart->copy()->endOfMonth()->format('Y-m-d'),
            ])->sum('purchase_order_net_value');

        $currentMonthGr = $lastMonthValue > 0
            ? round(($currentMonthValue - $lastMonthValue) / $lastMonthValue * 100, 1) : 0;

        $currentMonthLabel = $lastMonthStart->format('M Y');

        // Last 3 months
        $last3To   = $to->copy()->endOfMonth();
        $last3From = $to->copy()->subMonths(2)->startOfMonth();
        if ($last3From->lt($from)) $last3From = $from->copy();

        $prior3To   = $last3From->copy()->subDay()->endOfMonth();
        $prior3From = $prior3To->copy()->subMonths(2)->startOfMonth();
        if ($prior3From->lt($from)) $prior3From = $from->copy();

        $last3Value = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$last3From->format('Y-m-d'), $last3To->format('Y-m-d')])
            ->sum('purchase_order_net_value');

        $prior3Value = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$prior3From->format('Y-m-d'), $prior3To->format('Y-m-d')])
            ->sum('purchase_order_net_value');

        $last3Gr = $prior3Value > 0
            ? round(($last3Value - $prior3Value) / $prior3Value * 100, 1) : 0;

        // YTD
        $ytdFrom = Carbon::create($to->year, 1, 1)->format('Y-m-d');
        if ($from->gt(Carbon::parse($ytdFrom))) $ytdFrom = $dateFrom;

        $ytd = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$ytdFrom, $dateTo])
            ->sum('purchase_order_net_value');

        $ytdMonths  = max(1, Carbon::parse($ytdFrom)->diffInMonths(Carbon::parse($dateTo)) + 1);
        $avgMonthly = $ytd / $ytdMonths;

        // Destination countries
        $activeDestinations = (clone $periodQuery)->whereNotNull('destination_country')
            ->distinct('destination_country')->count('destination_country');

        $totalDestinations = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereNotNull('destination_country')->distinct('destination_country')->count('destination_country');

        // Active customers
        $activeCustomers = (clone $periodQuery)->whereNotNull('customer_name')
            ->distinct('customer_name')->count('customer_name');

        // Best / worst month
        $monthlyRows = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, SUM(purchase_order_net_value) as value")
            ->groupBy('period')->orderByDesc('value')->get();

        $bestMonth  = $monthlyRows->first();
        $worstMonth = $monthlyRows->last();

        // Total FCL count
        $totalFcl = (clone $periodQuery)->sum('full_container_load_count');

        return [
            'current_month'        => (float) $currentMonthValue,
            'current_month_gr'     => $currentMonthGr,
            'current_month_label'  => $currentMonthLabel,
            'last_3_months'        => (float) $last3Value,
            'last_3_months_gr'     => $last3Gr,
            'last_3_label'         => $last3From->format('M Y') . ' → ' . $last3To->format('M Y'),
            'ytd'                  => (float) $ytd,
            'ytd_label'            => Carbon::parse($ytdFrom)->format('M Y') . ' → ' . Carbon::parse($dateTo)->format('M Y'),
            'avg_monthly'          => round($avgMonthly, 2),
            'avg_order_value'      => round($avgOrderValue, 2),
            'total_orders'         => $totalOrders,
            'active_destinations'  => $activeDestinations,
            'total_destinations'   => $totalDestinations,
            'active_customers'     => $activeCustomers,
            'total_fcl'            => (int) $totalFcl,
            'best_month_label'     => $bestMonth?->period,
            'best_month_value'     => (float) ($bestMonth?->value ?? 0),
            'worst_month_label'    => $worstMonth?->period,
            'worst_month_value'    => (float) ($worstMonth?->value ?? 0),
        ];
    }

    // ── Monthly Trend ─────────────────────────────────────────

    private function buildMonthlyTrend($companyId, $dateFrom, $dateTo): array
    {
        $rows = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, DATE_FORMAT(`date`,'%Y%m')+0 as sort_key, SUM(purchase_order_net_value) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        return $rows->map(fn($r) => [
            'period' => $r->period,
            'value'  => (float) $r->value,
        ])->values()->toArray();
    }

    // ── Breakdowns ────────────────────────────────────────────

    private function buildBreakdowns($companyId, $dateFrom, $dateTo): array
    {
        $dimFields = $this->getActiveDimensionFields($companyId);

        // Exclude too-granular fields
        $excludeFromBreakdown = [
            'purchase_order_number', 'booking_number', 'customer_name',
            'product_item', 'consignee', 'broker', 'clearance_agent',
            'inspection_company', 'export_bank',
        ];
        $dimFields = array_filter($dimFields, fn($f) => !in_array($f, $excludeFromBreakdown));

        $breakdowns = [];
        foreach ($dimFields as $field) {
            $label = self::FIELDS[$field] ?? $field;

            $rows = ExportSalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull($field)->where($field, '!=', '')
                ->selectRaw("`$field` as label, SUM(purchase_order_net_value) as value")
                ->groupBy($field)->orderByDesc('value')->limit(15)->get();

            $total = $rows->sum('value');
            $accum = 0;
            $rowsData = $rows->map(function ($r) use ($total, &$accum) {
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

            $rows = ExportSalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull($field)->where($field, '!=', '')
                ->selectRaw("`$field` as label, SUM(purchase_order_net_value) as value")
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

    // ── PO Status Summary (unique to Export Sales) ─────────────

    private function buildPoStatus($companyId, $dateFrom, $dateTo): array
    {
        $activeFields = $this->getActiveFields($companyId);
        if (!in_array('purchase_order_status', $activeFields)) return [];

        $rows = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotNull('purchase_order_status')
            ->selectRaw('purchase_order_status as status,
                COUNT(DISTINCT purchase_order_number) as order_count,
                SUM(purchase_order_net_value) as total_value')
            ->groupBy('purchase_order_status')
            ->orderByDesc('total_value')
            ->get();

        $grandTotal = $rows->sum('total_value');

        return $rows->map(fn($r) => [
            'status'      => $r->status,
            'order_count' => (int) $r->order_count,
            'total_value' => (float) $r->total_value,
            'pct'         => $grandTotal > 0 ? round($r->total_value / $grandTotal * 100, 1) : 0,
        ])->values()->toArray();
    }

    // ── Takeaway Popup ────────────────────────────────────────

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
        $selectedValue = $request->selected_value;
        $activeFields  = $this->getActiveFields($companyId);

        // Full ranking
        $rankingRows = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotNull($field)->where($field, '!=', '')
            ->selectRaw("`$field` as label, SUM(purchase_order_net_value) as value")
            ->groupBy($field)->orderByDesc('value')->get();

        $totalAll = $rankingRows->sum('value');

        $ranking = $rankingRows->map(fn($r) => [
            'label' => $r->label,
            'value' => (float) $r->value,
            'pct'   => $totalAll > 0 ? round($r->value / $totalAll * 100, 1) : 0,
        ])->values()->toArray();

        $statLabel = $selectedValue ?? ($rankingRows->first()?->label ?? '');

        $statQuery = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where($field, $statLabel);

        $stats = [];

        $itemValue = (float)(clone $statQuery)->sum('purchase_order_net_value');
        $stats[] = ['label' => 'PO Net Value', 'value' => number_format($itemValue, 0, '.', ',')];

        $stats[] = [
            'label' => '% of Total',
            'value' => $totalAll > 0 ? round($itemValue / $totalAll * 100, 1) . '%' : '—',
        ];

        if (in_array('purchase_order_number', $activeFields)) {
            $stats[] = [
                'label' => 'PO Count',
                'value' => (clone $statQuery)->whereNotNull('purchase_order_number')
                    ->distinct('purchase_order_number')->count('purchase_order_number'),
            ];
        }

        if (in_array('customer_name', $activeFields) && $field !== 'customer_name') {
            $stats[] = [
                'label' => 'Customers',
                'value' => (clone $statQuery)->whereNotNull('customer_name')
                    ->distinct('customer_name')->count('customer_name'),
            ];
        }

        if (in_array('destination_country', $activeFields) && $field !== 'destination_country') {
            $stats[] = [
                'label' => 'Destinations',
                'value' => (clone $statQuery)->whereNotNull('destination_country')
                    ->distinct('destination_country')->count('destination_country'),
            ];
        }

        if (in_array('product_category', $activeFields) && $field !== 'product_category') {
            $stats[] = [
                'label' => 'Product Categories',
                'value' => (clone $statQuery)->whereNotNull('product_category')
                    ->distinct('product_category')->count('product_category'),
            ];
        }

        if (in_array('shipping_line', $activeFields) && $field !== 'shipping_line') {
            $stats[] = [
                'label' => 'Shipping Lines',
                'value' => (clone $statQuery)->whereNotNull('shipping_line')
                    ->distinct('shipping_line')->count('shipping_line'),
            ];
        }

        if (in_array('full_container_load_count', $activeFields)) {
            $stats[] = [
                'label' => 'Total FCL',
                'value' => (int)(clone $statQuery)->sum('full_container_load_count'),
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

        $monthlyRows = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(`date`,'%Y-%b') as period, DATE_FORMAT(`date`,'%Y%m')+0 as sort_key, SUM(purchase_order_net_value) as value")
            ->groupBy('period', 'sort_key')->orderBy('sort_key')->get();

        if ($monthlyRows->count() >= 2) {
            $values     = $monthlyRows->pluck('value')->map(fn($v) => (float) $v);
            $last       = $values->last();
            $prev       = $values->slice(-2, 1)->first();
            $mom        = $prev > 0 ? round(($last - $prev) / $prev * 100, 1) : 0;
            $lastPeriod = $monthlyRows->last()->period;

            if ($mom >= 20) {
                $insights[] = ['type' => 'positive', 'icon' => '🚀', 'title' => 'Strong Export Growth', 'body' => "$lastPeriod showed {$mom}% growth vs previous month — exceptional export momentum."];
            } elseif ($mom >= 5) {
                $insights[] = ['type' => 'positive', 'icon' => '📈', 'title' => 'Positive Export Momentum', 'body' => "$lastPeriod grew {$mom}% month-over-month — steady upward trend."];
            } elseif ($mom <= -20) {
                $insights[] = ['type' => 'danger', 'icon' => '🚨', 'title' => 'Sharp Export Drop', 'body' => "$lastPeriod dropped {$mom}% vs previous month — requires immediate attention."];
            } elseif ($mom <= -5) {
                $insights[] = ['type' => 'warning', 'icon' => '⚠️', 'title' => 'Declining Export Trend', 'body' => "$lastPeriod declined {$mom}% month-over-month — monitor closely."];
            }

            // Volatility
            $max = $values->max();
            $min = $values->min();
            if ($max > 0 && $min > 0) {
                $spread = round(($max - $min) / $max * 100, 1);
                if ($spread > 60) {
                    $bestPeriod = $monthlyRows->firstWhere('value', $max)?->period;
                    $insights[] = ['type' => 'warning', 'icon' => '📊', 'title' => 'High Export Volatility', 'body' => "Export value fluctuated {$spread}% between best and worst months. Best: $bestPeriod. Consider stabilising shipment schedule."];
                }
            }
        }

        // Market concentration — destination country
        $activeFields = $this->getActiveDimensionFields($companyId);
        $checkFields  = array_intersect($activeFields, ['destination_country', 'customer_name', 'product_category']);

        foreach ($checkFields as $field) {
            $topRow = ExportSalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->whereNotNull($field)->where($field, '!=', '')
                ->selectRaw("`$field` as label, SUM(purchase_order_net_value) as value")
                ->groupBy($field)->orderByDesc('value')->first();

            $totalValue = ExportSalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$dateFrom, $dateTo])->sum('purchase_order_net_value');

            if ($topRow && $totalValue > 0) {
                $pct        = round($topRow->value / $totalValue * 100, 1);
                $fieldLabel = self::FIELDS[$field] ?? $field;
                if ($pct >= 50) {
                    $insights[] = ['type' => 'danger', 'icon' => '⚡', 'title' => "High Concentration Risk — {$fieldLabel}", 'body' => "{$topRow->label} accounts for {$pct}% of export value. Heavy dependence on a single {$fieldLabel} is a risk."];
                } elseif ($pct >= 35) {
                    $insights[] = ['type' => 'warning', 'icon' => '🎯', 'title' => "Concentration Watch — {$fieldLabel}", 'body' => "{$topRow->label} represents {$pct}% of export value. Consider diversifying your {$fieldLabel} mix."];
                }
            }
        }

        // PO Status alert — if many open/pending POs
        $openPOs = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotNull('purchase_order_status')
            ->whereIn('purchase_order_status', ['Open', 'Pending', 'In Progress', 'open', 'pending'])
            ->distinct('purchase_order_number')->count('purchase_order_number');

        if ($openPOs > 0) {
            $insights[] = ['type' => 'warning', 'icon' => '📋', 'title' => 'Open POs Require Attention', 'body' => "{$openPOs} purchase order(s) are still open or pending in this period. Review to ensure timely shipment."];
        }

        // Above/below average recent performance
        $totalValue = ExportSalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])->sum('purchase_order_net_value');

        if ($totalValue > 0 && $monthlyRows->count() > 0) {
            $avgMonthly = $totalValue / $monthlyRows->count();
            $lastVal    = (float) ($monthlyRows->last()?->value ?? 0);
            if ($lastVal > $avgMonthly * 1.3) {
                $insights[] = ['type' => 'positive', 'icon' => '⭐', 'title' => 'Above-Average Recent Performance', 'body' => 'Last month was ' . round(($lastVal / $avgMonthly - 1) * 100, 1) . '% above the period monthly average — strong export finish.'];
            } elseif ($lastVal < $avgMonthly * 0.7) {
                $insights[] = ['type' => 'warning', 'icon' => '📉', 'title' => 'Below-Average Recent Performance', 'body' => 'Last month was ' . round((1 - $lastVal / $avgMonthly) * 100, 1) . '% below the period monthly average — needs attention.'];
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

        DB::table('export_sales_dashboard_notes')->updateOrInsert(
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

        $notes = DB::table('export_sales_dashboard_notes')
            ->where('portfolio_company_id', $companyId)
            ->where('date_from', $request->date_from)
            ->where('date_to', $request->date_to)
            ->join('users', 'users.id', '=', 'export_sales_dashboard_notes.created_by')
            ->select('export_sales_dashboard_notes.*', 'users.name as author')
            ->orderByDesc('export_sales_dashboard_notes.updated_at')
            ->get();

        return response()->json(['notes' => $notes]);
    }

    public function updateNote(Request $request, $companyId, $noteId)
    {
        $request->validate(['note' => ['required', 'string', 'max:50000']]);

        DB::table('export_sales_dashboard_notes')
            ->where('id', $noteId)
            ->where('portfolio_company_id', $companyId)
            ->where('created_by', Auth::id())
            ->update(['note' => $request->note, 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function deleteNote($companyId, $noteId)
    {
        DB::table('export_sales_dashboard_notes')
            ->where('id', $noteId)
            ->where('portfolio_company_id', $companyId)
            ->where('created_by', Auth::id())
            ->delete();

        return response()->json(['success' => true]);
    }
}