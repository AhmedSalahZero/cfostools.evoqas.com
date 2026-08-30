<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesPortfolioCompany;
use App\Models\ComparisonDashboard;
use App\Models\ComparisonDashboardNote;
use App\Models\PortfolioCompany;
use App\Models\SalesData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComparisonDashboardController extends Controller
{
    use AuthorizesPortfolioCompany;

    private function authorizeSalesCompany($companyId): PortfolioCompany
    {
        return $this->authorizeCompany((int) $companyId, 'sales_analysis');
    }

    // Extra business dimensions we CAN analyze if the company's sales
    // data actually has them. Not every company uploads Branch, Sales
    // Channel, etc. — a section only appears for a dimension that has
    // at least one real (non-empty) value for this company, so we never
    // show an empty/misleading "Branch Analysis" for a company that
    // never used that column.
    private const DIMENSIONS = [
        'branch'          => 'Branch',
        'sales_channel'   => 'Sales Channel',
        'business_sector' => 'Business Sector',
        'business_unit'   => 'Business Unit',
        'zone'            => 'Zone',
        'country'         => 'Country',
    ];

    // ── Pages ──────────────────────────────────────────────────

    public function index($companyId)
    {
        $company = $this->authorizeSalesCompany($companyId);
        $dashboards = ComparisonDashboard::where('portfolio_company_id', $companyId)
            ->orderByDesc('created_at')->get();

        return Inertia::render('ComparisonDashboard/Index', [
            'company'    => $company,
            'dashboards' => $dashboards,
        ]);
    }

    public function create($companyId)
    {
        $company = $this->authorizeSalesCompany($companyId);
        return Inertia::render('ComparisonDashboard/Create', [
            'company' => $company,
        ]);
    }

    public function store(Request $request, $companyId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'periods'         => ['required', 'array', 'min:2', 'max:5'],
            'periods.*.label' => ['required', 'string', 'max:60'],
            'periods.*.from'  => ['required', 'date'],
            'periods.*.to'    => ['required', 'date', 'after_or_equal:periods.*.from'],
        ]);

        $dashboard = ComparisonDashboard::create([
            'portfolio_company_id' => $companyId,
            'name'                 => $request->name,
            'periods'              => $request->periods,
            'is_public'            => false,
            'created_by'           => auth()->id(),
        ]);

        return redirect()->route('comparison-dashboard.show', ['company' => $companyId, 'dashboard' => $dashboard->id]);
    }

    public function show($companyId, $dashboardId)
    {
        $company   = $this->authorizeSalesCompany($companyId);
        $dashboard = ComparisonDashboard::where('portfolio_company_id', $companyId)->findOrFail($dashboardId);

        return Inertia::render('ComparisonDashboard/Show', [
            'company'   => $company,
            'dashboard' => $dashboard,
            ...$this->buildAnalysis($companyId, $dashboard->periods, $dashboardId),
        ]);
    }

    public function destroy($companyId, $dashboardId)
    {
        $this->authorizeSalesCompany($companyId);
        ComparisonDashboard::where('portfolio_company_id', $companyId)->findOrFail($dashboardId)->delete();
        return redirect()->route('comparison-dashboard.index', $companyId);
    }

    public function toggleShare($companyId, $dashboardId)
    {
        $this->authorizeSalesCompany($companyId);
        $dashboard = ComparisonDashboard::where('portfolio_company_id', $companyId)->findOrFail($dashboardId);
        $dashboard->is_public = ! $dashboard->is_public;
        $dashboard->save();

        return response()->json([
            'is_public'   => $dashboard->is_public,
            'share_token' => $dashboard->share_token,
        ]);
    }

    // ── Public, unauthenticated share view ──────────────────────

    public function publicShow($token)
    {
        $dashboard = ComparisonDashboard::where('share_token', $token)->where('is_public', true)->first();
        abort_unless($dashboard, 404, 'This report is no longer available. Ask whoever shared it to reactivate the link.');

        $companyId = $dashboard->portfolio_company_id;
        $company   = PortfolioCompany::findOrFail($companyId);

        return Inertia::render('ComparisonDashboard/PublicShow', [
            'companyName' => $company->name,
            'dashboard'   => ['name' => $dashboard->name, 'periods' => $dashboard->periods],
            ...$this->buildAnalysis($companyId, $dashboard->periods, $dashboard->id),
        ]);
    }

    // Runs every computation, generates the draft narrative for each
    // section, then lets any saved note OVERRIDE that draft — exactly
    // the "auto-generated but editable" behavior requested. Every
    // section that appears on the page (including the ones added
    // later — Executive Summary, Top 100, Product Concentration, and
    // any available business-dimension section) gets a narrative and a
    // note entry, so the editable-commentary experience is consistent
    // across the whole report, not just some sections.
    private function buildAnalysis($companyId, array $periods, $dashboardId): array
    {
        $zoomOut   = $this->computeZoomOut($companyId, $periods);
        $zoomIn    = $this->computeZoomIn($companyId, $periods);
        $vanishing = $this->computeVanishing($companyId, $periods);
        $top50     = $this->computeTop50($companyId, $periods); // now returns the Top 100 — see computeTop50()/top50For()
        $heroPairs = $this->computeHeroPairs($companyId, $periods);
        $takeaways = $this->computeTakeaways($zoomIn, $vanishing);

        $productConcentration = [];
        foreach ($periods as $p) {
            $productConcentration[] = ['period' => $p, 'categories' => $this->computeProductConcentration($companyId, $p)];
        }

        // Extra business-dimension sections (Branch, Sales Channel,
        // Business Sector, Business Unit, Zone, Country) — only the
        // ones that actually have data for this company are computed
        // and shown.
        $dimensions = [];
        foreach ($this->availableDimensions($companyId) as $field => $label) {
            $dimensions[] = $this->computeDimensionSection($companyId, $periods, $field, $label);
        }

        $savedNotes = ComparisonDashboardNote::where('comparison_dashboard_id', $dashboardId)->get()->keyBy('section_key');

        $narratives = [];
        $narratives['hero_summary'] = $this->narrativeHero($heroPairs);
        $narratives['zoom_out']     = $this->narrativeZoomOut($companyId, $periods, $zoomOut);
        foreach ($takeaways as $t) {
            $narratives[$t['key']] = "{$t['stat']} {$t['text']}";
        }
        foreach ($zoomIn as $pair) {
            $narratives[$pair['section_key']] = $this->narrativeZoomIn($pair);
        }
        foreach ($vanishing as $pair) {
            $narratives[$pair['section_key']] = $this->narrativeVanishing($pair);
        }
        $narratives['top_customers_products'] = $this->narrativeTopRanked($top50);
        $narratives['product_concentration']  = $this->narrativeProductConcentration($productConcentration);
        foreach ($dimensions as $dim) {
            $narratives[$dim['section_key']] = $this->narrativeDimension($dim['label'], $dim);
        }

        // Merge: a saved note always wins over the freshly generated
        // draft, since it represents the user's own edited commentary.
        $notes = [];
        foreach ($narratives as $key => $draft) {
            $notes[$key] = [
                'note'          => $savedNotes[$key]->note ?? $draft,
                'is_auto'       => ! isset($savedNotes[$key]),
                'auto_fallback' => $draft,
            ];
        }

        return [
            'zoomOut'   => $zoomOut,
            'zoomIn'    => $zoomIn,
            'vanishing' => $vanishing,
            'top50'     => $top50,
            'heroPairs' => $heroPairs,
            'takeaways' => $takeaways,
            'productConcentration' => $productConcentration,
            'dimensions' => $dimensions,
            'notes'     => $notes,
        ];
    }

    // ── Notes (reuses the same pattern as sales_dashboard_notes) ─

    public function saveNote(Request $request, $companyId, $dashboardId)
    {
        $this->authorizeSalesCompany($companyId);
        $request->validate([
            'section_key' => ['required', 'string', 'max:60'],
            'note'        => ['required', 'string', 'max:50000'],
        ]);

        ComparisonDashboardNote::updateOrCreate(
            ['comparison_dashboard_id' => $dashboardId, 'section_key' => $request->section_key],
            ['note' => $request->note, 'updated_by' => auth()->id()]
        );

        return response()->json(['success' => true]);
    }

    public function getNotes($companyId, $dashboardId)
    {
        $this->authorizeSalesCompany($companyId);
        return response()->json([
            'notes' => ComparisonDashboardNote::where('comparison_dashboard_id', $dashboardId)->get()->keyBy('section_key'),
        ]);
    }

    public function deleteNote($companyId, $dashboardId, $noteId)
    {
        $this->authorizeSalesCompany($companyId);
        ComparisonDashboardNote::where('comparison_dashboard_id', $dashboardId)->findOrFail($noteId)->delete();
        return response()->json(['success' => true]);
    }

    // ── Computation: Zoom Out ────────────────────────────────────
    // Headline KPIs per period, always computed live — never cached —
    // so reloading a saved dashboard (or its public link) always shows
    // whatever the sales data looks like right now.

    private function computeZoomOut($companyId, array $periods): array
    {
        $rows = [];
        foreach ($periods as $p) {
            $agg  = $this->periodAgg($companyId, $p);
            $days = $this->periodDays($p);
            $rows[] = [
                'label'        => $p['label'],
                'from'         => $p['from'],
                'to'           => $p['to'],
                'days'         => $days,
                'net_sales'    => $agg['net_sales'],
                'daily_avg'    => $days > 0 ? $agg['net_sales'] / $days : 0,
                'qty'          => $agg['qty'],
                'transactions' => $agg['transactions'],
                'customers'    => $agg['customers'],
                // Two DIFFERENT averages, kept separate on purpose — an
                // earlier version showed this one under the label "Avg
                // Value / Transaction" even though it's actually net
                // sales ÷ units sold, not ÷ transactions:
                'avg_price_per_unit'        => $agg['qty'] > 0 ? $agg['net_sales'] / $agg['qty'] : 0,
                'avg_value_per_transaction' => $agg['transactions'] > 0 ? $agg['net_sales'] / $agg['transactions'] : 0,
            ];
        }

        // Period-over-period growth %, for the trend chart's growth line.
        // Uses the SAME calendar-month alignment as everywhere else when
        // consecutive periods differ meaningfully in length, so the
        // growth line is never distorted by period length.
        foreach ($periods as $i => $p) {
            if ($i === 0) { $rows[$i]['growth_pct'] = null; continue; }
            [$cmpPrev, $cmpCur, $wasAligned] = $this->alignForComparison($periods[$i - 1], $p);
            if ($wasAligned) {
                $base = $this->periodAgg($companyId, $cmpPrev)['net_sales'];
                $cur  = $this->periodAgg($companyId, $cmpCur)['net_sales'];
            } else {
                $base = $rows[$i - 1]['net_sales'];
                $cur  = $rows[$i]['net_sales'];
            }
            $rows[$i]['growth_pct'] = $base > 0 ? round(($cur - $base) / $base * 100, 1) : null;
        }

        return $rows;
    }

    // Shared aggregate query for any single period — net sales, quantity,
    // transaction count, distinct customer count.
    private function periodAgg($companyId, array $period): array
    {
        $agg = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$period['from'], $period['to']])
            ->selectRaw('
                SUM(net_sales_value) as net_sales,
                SUM(quantity) as qty,
                COUNT(DISTINCT document_number) as transactions,
                COUNT(DISTINCT customer_name) as customers
            ')->first();

        return [
            'net_sales'    => (float) ($agg->net_sales ?? 0),
            'qty'          => (float) ($agg->qty ?? 0),
            'transactions' => (int) ($agg->transactions ?? 0),
            'customers'    => (int) ($agg->customers ?? 0),
        ];
    }

    // Inclusive day count for a period, e.g. Jan 1 - Jan 31 = 31 days.
    private function periodDays(array $period): int
    {
        return \Carbon\Carbon::parse($period['from'])->diffInDays(\Carbon\Carbon::parse($period['to'])) + 1;
    }

    // Two periods are "comparable" (safe to compare raw totals) only when
    // they run for roughly the same length of time. A full year vs. 7
    // months will always look like a decline on raw totals alone, purely
    // because there's less time in the shorter period — not because the
    // business actually shrank.
    private function periodsComparable(array $pA, array $pB): bool
    {
        $daysA = $this->periodDays($pA);
        $daysB = $this->periodDays($pB);
        $ratio = min($daysA, $daysB) / max($daysA, $daysB);
        return $ratio >= 0.9; // within ~10% of each other
    }

    // When two periods are meaningfully different lengths (e.g. Year 2025
    // vs. Jan–Jul 2026), pull the SAME calendar months for the longer one
    // instead of its full span — "Year 2025" becomes "Year 2025 (Jan 1 –
    // Jul 31, 7 mo)" — so every comparison in this pair is genuinely
    // apples-to-apples, and the month count is always spelled out rather
    // than left for the reader to work out from the date range.
    // Returns [periodA, periodB, wasAligned].
    private function alignForComparison(array $pA, array $pB): array
    {
        if ($this->periodsComparable($pA, $pB)) {
            return [$pA, $pB, false];
        }
        if ($this->periodDays($pA) > $this->periodDays($pB)) {
            return [$this->narrowToMatch($pA, $pB), $this->withMonthCount($pB), true];
        }
        return [$this->withMonthCount($pA), $this->narrowToMatch($pB, $pA), true];
    }

    // Appends an explicit "(N mo)" to a period's label, so the shorter
    // side of an aligned pair states its own month count too — not just
    // the narrowed side.
    private function withMonthCount(array $period): array
    {
        $months = round($this->periodDays($period) / 30.44);
        $period['label'] = "{$period['label']} ({$months} mo)";
        return $period;
    }

    // Narrows $longer down to the same month-day span as $shorter, keeping
    // $longer's own year (and preserving a cross-year span if $shorter has one).
    private function narrowToMatch(array $longer, array $shorter): array
    {
        $shortFrom = \Carbon\Carbon::parse($shorter['from']);
        $shortTo   = \Carbon\Carbon::parse($shorter['to']);
        $longFrom  = \Carbon\Carbon::parse($longer['from']);
        $yearOffset = $shortTo->year - $shortFrom->year;

        $newFrom = \Carbon\Carbon::create($longFrom->year, $shortFrom->month, $shortFrom->day);
        $newTo   = \Carbon\Carbon::create($longFrom->year + $yearOffset, $shortTo->month, $shortTo->day);
        $months  = round(($newFrom->diffInDays($newTo) + 1) / 30.44);

        return [
            'from'  => $newFrom->format('Y-m-d'),
            'to'    => $newTo->format('Y-m-d'),
            'label' => $longer['label'] . ' (' . $newFrom->format('M j') . ' – ' . $newTo->format('M j') . ", {$months} mo)",
        ];
    }

    // ── Computation: Zoom In ──────────────────────────────────────
    // For every CONSECUTIVE pair of chosen periods: real Customer Nature
    // for each period (same methodology as the rest of the app) + the
    // biggest category and sales-person movements between them.

    private function computeZoomIn($companyId, array $periods): array
    {
        $pairs = [];
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $pA = $periods[$i];
            $pB = $periods[$i + 1];
            [$cmpA, $cmpB, $wasAligned] = $this->alignForComparison($pA, $pB);

            $catA = $this->groupedValues($companyId, $cmpA, 'product_category');
            $catB = $this->groupedValues($companyId, $cmpB, 'product_category');
            $categoryBreakdown = $catA->keys()->merge($catB->keys())->unique()->map(function ($c) use ($catA, $catB) {
                $a = (float) ($catA[$c] ?? 0);
                $b = (float) ($catB[$c] ?? 0);
                $change = $b - $a;
                return ['label' => $c, 'value_a' => $a, 'value_b' => $b, 'change' => $change, 'change_pct' => $a > 0 ? round($change / $a * 100, 1) : null];
            })->sortByDesc(fn($r) => abs($r['change']))->values()->take(10);

            $spA = $this->groupedValues($companyId, $cmpA, 'sales_person');
            $spB = $this->groupedValues($companyId, $cmpB, 'sales_person');
            $salespersonBreakdown = $spA->keys()->merge($spB->keys())->unique()->map(function ($s) use ($spA, $spB) {
                $a = (float) ($spA[$s] ?? 0);
                $b = (float) ($spB[$s] ?? 0);
                $change = $b - $a;
                return ['label' => $s, 'value_a' => $a, 'value_b' => $b, 'change' => $change, 'change_pct' => $a > 0 ? round($change / $a * 100, 1) : null];
            })->sortByDesc(fn($r) => abs($r['change']))->values()->take(10);

            $pairs[] = [
                'section_key'           => "zoom_in_{$i}_" . ($i + 1),
                'period_a'              => $pA,
                'period_b'              => $pB,
                'compare_period_a'      => $cmpA, // the ACTUAL date range used below — may be narrowed for fairness
                'compare_period_b'      => $cmpB,
                'was_aligned'           => $wasAligned,
                // Customer Nature categorization is inherently full-calendar-year
                // (same methodology as the rest of the app) and is unaffected by
                // this narrowing — only the $ totals shown per category use the
                // aligned window, which is exactly what we want here.
                'customer_nature_a'     => $this->computeCustomerNature($companyId, $cmpA),
                'customer_nature_b'     => $this->computeCustomerNature($companyId, $cmpB),
                'category_breakdown'    => $categoryBreakdown,
                'salesperson_breakdown' => $salespersonBreakdown,
            ];
        }
        return $pairs;
    }

    // ── Customer Nature — same methodology used everywhere else in the
    // app (Sales Analysis, Sales Dashboard): categorization is inherently
    // a calendar-year concept (was this customer active in Y-1, Y-2...),
    // anchored on the year containing the period's end date. Sales value
    // per customer is scoped to the period's ACTUAL date range though, so
    // a quarter-long period correctly shows only that quarter's value —
    // it's only the New/Repeating/Stop/Dead categorization itself that
    // looks at full calendar years, exactly as it does elsewhere.

    private function computeCustomerNature($companyId, array $period): array
    {
        $currentYear = (int) date('Y', strtotime($period['to']));

        $years = [];
        for ($i = 0; $i <= 4; $i++) {
            $y = $currentYear - $i;
            $years[$i] = SalesData::where('portfolio_company_id', $companyId)
                ->whereYear('date', $y)->whereNotNull('customer_name')
                ->pluck('customer_name')->unique();
        }
        [$setY, $setY1, $setY2, $setY3, $setY4] = $years;

        $buckets = [
            'new'              => $setY->diff($setY1)->diff($setY2)->diff($setY3)->values(),
            'repeating'        => $setY->intersect($setY1)->diff($setY2)->diff($setY3)->diff($setY4)->values(),
            'active'           => $setY->intersect($setY1)->intersect($setY2)->values(),
            'stop'             => $setY1->diff($setY)->values(),
            'dead'             => $setY2->diff($setY1)->diff($setY)->values(),
            'stop_reactivated' => $setY->intersect($setY2)->diff($setY1)->values(),
            'dead_reactivated' => $setY->intersect($setY3)->diff($setY2)->diff($setY1)->values(),
        ];

        $salesByCustomer = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$period['from'], $period['to']])
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw('customer_name, SUM(net_sales_value) as total_sales')
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        // "Stop" and "Dead" have zero sales in the current period by
        // definition — show their sales from the last year they were
        // genuinely active instead, so it's clear how much is being lost.
        $salesLastYear = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear - 1)
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw('customer_name, SUM(net_sales_value) as total_sales')
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        $salesTwoYearsAgo = SalesData::where('portfolio_company_id', $companyId)
            ->whereYear('date', $currentYear - 2)
            ->whereNotNull('customer_name')->where('customer_name', '!=', '')
            ->selectRaw('customer_name, SUM(net_sales_value) as total_sales')
            ->groupBy('customer_name')->get()->keyBy('customer_name');

        $pastPeriodSales = ['stop' => $salesLastYear, 'dead' => $salesTwoYearsAgo];

        $categories = collect($buckets)->map(function ($customers, $key) use ($pastPeriodSales, $salesByCustomer) {
            $salesMap = $pastPeriodSales[$key] ?? $salesByCustomer;
            $total = $customers->sum(fn($name) => (float) ($salesMap[$name]->total_sales ?? 0));
            return ['label' => $key, 'count' => $customers->count(), 'total_sales' => $total];
        })->values();

        $byLabel = $categories->keyBy('label');
        $totalCustomers = $categories->sum('count');
        $retentionRate = $totalCustomers > 0
            ? round((($byLabel['repeating']['count'] ?? 0) + ($byLabel['active']['count'] ?? 0)) / $totalCustomers * 100, 1) : 0;

        return [
            'year'           => $currentYear,
            'categories'     => $categories,
            'retention_rate' => $retentionRate,
            'churn_dead'     => $byLabel['dead']['count'] ?? 0,
            'reactivated'    => $byLabel['stop_reactivated']['count'] ?? 0,
            'new_this_year'  => $byLabel['new']['count'] ?? 0,
        ];
    }

    // Sum of net_sales_value grouped by a given field, for one period.
    private function groupedValues($companyId, array $period, string $field)
    {
        return SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$period['from'], $period['to']])
            ->whereNotNull($field)->where($field, '!=', '')
            ->selectRaw("`$field` as label, SUM(net_sales_value) as value")
            ->groupBy($field)->get()->pluck('value', 'label');
    }

    // ── Computation: Vanishing Stars ─────────────────────────────
    // For every consecutive period pair: products AND customers that were
    // MEANINGFUL in the earlier period, then collapsed to under 5% of
    // that value in the later one. "Meaningful" is 0.5% of that period's
    // total net sales — a relative threshold, not a fixed currency
    // amount, so this behaves consistently for a small company and a
    // large one, and doesn't assume any particular currency.

    private function computeVanishing($companyId, array $periods): array
    {
        $pairs = [];
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $pA = $periods[$i];
            $pB = $periods[$i + 1];
            [$cmpA, $cmpB, $wasAligned] = $this->alignForComparison($pA, $pB);

            $periodTotalA   = $this->periodAgg($companyId, $cmpA)['net_sales'];
            $thresholdPct   = 0.5;
            $thresholdValue = $periodTotalA > 0 ? $periodTotalA * ($thresholdPct / 100) : 0;

            $products  = $this->findVanished($companyId, $cmpA, $cmpB, 'product_item', $thresholdValue);
            $customers = $this->findVanished($companyId, $cmpA, $cmpB, 'customer_name', $thresholdValue);

            $pairs[] = [
                'section_key'      => "vanish_{$i}_" . ($i + 1),
                'period_a'         => $pA,
                'period_b'         => $pB,
                'compare_period_a' => $cmpA,
                'compare_period_b' => $cmpB,
                'was_aligned'      => $wasAligned,
                'threshold_pct'    => $thresholdPct,
                'threshold_value'  => $thresholdValue,
                'products'         => $products['all'],
                'products_count'   => $products['count'],
                'products_total'   => $products['total'],
                'products_cutoff'  => $products['cutoff_count'],
                'customers'        => $customers['all'],
                'customers_count'  => $customers['count'],
                'customers_total'  => $customers['total'],
                'customers_cutoff' => $customers['cutoff_count'],
            ];
        }
        return $pairs;
    }

    // Returns EVERY vanished item (not just a fixed Top N) so the frontend
    // can show a "Show More" expander, plus a cutoff_count marking how
    // many of them (by value, largest first) are needed to reach ~85% of
    // the total vanished value — that's what's shown by default.
    private function findVanished($companyId, array $pA, array $pB, string $field, float $thresholdValue): array
    {
        $valuesA = $this->groupedValues($companyId, $pA, $field);
        $valuesB = $this->groupedValues($companyId, $pB, $field);

        $matched = $valuesA->filter(fn($v, $name) => $thresholdValue > 0 && $v >= $thresholdValue && ((float) ($valuesB[$name] ?? 0)) < $v * 0.05)
            ->map(fn($v, $name) => ['name' => $name, 'value_a' => (float) $v, 'value_b' => (float) ($valuesB[$name] ?? 0)])
            ->sortByDesc('value_a')->values();

        $total = $matched->sum('value_a');
        $cumulative = 0;
        $cutoffCount = 0;
        foreach ($matched as $item) {
            if ($total > 0 && $cumulative >= $total * 0.85) break;
            $cumulative += $item['value_a'];
            $cutoffCount++;
        }

        return [
            'all'          => $matched,
            'count'        => $matched->count(),
            'total'        => $total,
            'cutoff_count' => $cutoffCount,
        ];
    }

    // ── Computation: Top 100 rank-movement (customers & products) ──
    // True global rank per period (every active entity ranked, not just
    // the Top 100), so a Top-100 entry can show its real rank in another
    // period even when that rank falls outside 100. Kept the method
    // names "computeTop50"/"top50For" for continuity with the rest of
    // the codebase, but they now return the Top 100 — see $limit below.
    // (With portfolios running into thousands of customers, a Top 50 cut
    // off too much of the picture.)

    private function computeTop50($companyId, array $periods): array
    {
        return [
            'customers' => $this->top50For($companyId, $periods, 'customer_name'),
            'products'  => $this->top50For($companyId, $periods, 'product_item'),
        ];
    }

    private function top50For($companyId, array $periods, string $field, int $limit = 100): array
    {
        $sortedByPeriod = [];
        $rankByPeriod   = [];
        foreach ($periods as $i => $p) {
            $sorted = $this->groupedValues($companyId, $p, $field)->filter(fn($v) => $v > 0)->sortDesc();
            $sortedByPeriod[$i] = $sorted;
            $rank = [];
            $pos = 1;
            foreach ($sorted as $name => $v) { $rank[$name] = $pos++; }
            $rankByPeriod[$i] = $rank;
        }

        $columns = [];
        foreach ($periods as $i => $p) {
            $topN = $sortedByPeriod[$i]->take($limit);
            // Both shares below are a % of the period's TOTAL net sales
            // (every customer/product, not just the Top N) — e.g.
            // "Top 10 share 60.5%" means the 10 largest customers made
            // up 60.5% of ALL net sales that period, not 60.5% of the
            // Top 100's own subtotal.
            $totalAll = $sortedByPeriod[$i]->sum();
            $topNShare  = $totalAll > 0 ? round($topN->sum() / $totalAll * 100, 1) : 0;
            $top10Share = $totalAll > 0 ? round($topN->take(10)->sum() / $totalAll * 100, 1) : 0;

            $rows = [];
            foreach ($topN as $name => $value) {
                $row = ['name' => $name, 'value' => (float) $value];
                foreach ($periods as $j => $pj) {
                    if ($j !== $i) $row["rank_{$j}"] = $rankByPeriod[$j][$name] ?? null;
                }
                $rows[] = $row;
            }
            $columns[] = [
                'period_index'    => $i, 'label' => $p['label'], 'rows' => $rows,
                'top10_share'     => $top10Share,   // Top 10   ÷ total net sales, %
                'top_n_share'     => $topNShare,    // Top $limit ÷ total net sales, %
                'total_net_sales' => $totalAll,     // period's full total, for reference
                'limit'           => $limit,
            ];
        }
        return $columns;
    }

    // ── Extra business dimensions (Branch / Sales Channel / Business
    // Sector / Business Unit / Zone / Country) ─────────────────────

    // Which of the candidate dimensions actually have data for this
    // company. A dimension only qualifies if at least one row has a
    // real, non-empty value for it.
    private function availableDimensions($companyId): array
    {
        $available = [];
        foreach (self::DIMENSIONS as $field => $label) {
            $hasData = SalesData::where('portfolio_company_id', $companyId)
                ->whereNotNull($field)->where($field, '!=', '')
                ->exists();
            if ($hasData) {
                $available[$field] = $label;
            }
        }
        return $available;
    }

    // For one dimension (e.g. "branch"): how net sales are split across
    // its values in each period (top 12 + an "Other" bucket for the
    // rest, so a company with 40 branches doesn't produce an unreadable
    // table), plus the biggest movers between each consecutive pair of
    // periods — same "biggest movement" pattern as Category/Sales
    // Person in Zoom In.
    private function computeDimensionSection($companyId, array $periods, string $field, string $label): array
    {
        $periodBreakdowns = [];
        foreach ($periods as $p) {
            $vals  = $this->groupedValues($companyId, $p, $field)->sortDesc();
            $total = $vals->sum();
            $top   = $vals->take(12);

            $rows = $top->map(fn($v, $name) => [
                'label' => $name,
                'value' => (float) $v,
                'pct'   => $total > 0 ? round($v / $total * 100, 1) : 0,
            ])->values();

            $otherCount = $vals->count() - $top->count();
            if ($otherCount > 0) {
                $otherValue = $total - $top->sum();
                $rows->push([
                    'label'    => "Other ({$otherCount})",
                    'value'    => $otherValue,
                    'pct'      => $total > 0 ? round($otherValue / $total * 100, 1) : 0,
                    'is_other' => true,
                ]);
            }

            $periodBreakdowns[] = [
                'period'         => $p,
                'rows'           => $rows->values(),
                'total'          => $total,
                'distinct_count' => $vals->count(),
            ];
        }

        $movements = [];
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $pA = $periods[$i];
            $pB = $periods[$i + 1];
            [$cmpA, $cmpB, $wasAligned] = $this->alignForComparison($pA, $pB);

            $valA = $this->groupedValues($companyId, $cmpA, $field);
            $valB = $this->groupedValues($companyId, $cmpB, $field);
            $rows = $valA->keys()->merge($valB->keys())->unique()->map(function ($name) use ($valA, $valB) {
                $a = (float) ($valA[$name] ?? 0);
                $b = (float) ($valB[$name] ?? 0);
                $change = $b - $a;
                return ['label' => $name, 'value_a' => $a, 'value_b' => $b, 'change' => $change, 'change_pct' => $a > 0 ? round($change / $a * 100, 1) : null];
            })->sortByDesc(fn($r) => abs($r['change']))->values()->take(10);

            $movements[] = [
                'period_a'         => $pA,
                'period_b'         => $pB,
                'compare_period_a' => $cmpA,
                'compare_period_b' => $cmpB,
                'was_aligned'      => $wasAligned,
                'rows'             => $rows,
            ];
        }

        return [
            'field'       => $field,
            'label'       => $label,
            'section_key' => "dim_{$field}",
            'periods'     => $periodBreakdowns,
            'movements'   => $movements,
        ];
    }

    // ── Narrative generation ───────────────────────────────────────
    // Drafts human-readable commentary from the computed numbers, used
    // to PRE-FILL each section's note. The user can overwrite it; their
    // saved version then always wins over a freshly regenerated draft.

    private function fmtM($n): string
    {
        $abs = abs($n);
        $sign = $n < 0 ? '-' : '';
        if ($abs >= 1_000_000) return "{$sign}" . number_format($abs / 1_000_000, 1) . 'M';
        if ($abs >= 1_000) return "{$sign}" . number_format($abs / 1_000, 1) . 'K';
        return "{$sign}" . number_format($abs, 0);
    }

    private function narrativeHero($heroPairs): string
    {
        if (empty($heroPairs)) return 'Add at least two periods to generate a summary.';

        $up   = collect($heroPairs)->where('raw_pct', '>=', 0)->count();
        $down = count($heroPairs) - $up;
        $biggest = collect($heroPairs)->sortByDesc(fn($p) => abs($p['raw_pct'] ?? 0))->first();
        $dir = ($biggest['raw_pct'] ?? 0) >= 0 ? 'growth' : 'a decline';

        $txt = 'Across the ' . count($heroPairs) . ' period-over-period comparison(s) shown, net sales grew in '
             . "{$up} and declined in {$down}. ";
        $txt .= "The sharpest move was {$biggest['label_a']} → {$biggest['label_b']}, {$dir} of " . abs($biggest['raw_pct']) . '% ('
             . $this->fmtM($biggest['net_sales_a']) . ' to ' . $this->fmtM($biggest['net_sales_b']) . ').';

        return $txt;
    }

    private function narrativeZoomOut($companyId, array $periods, array $rows): string
    {
        if (count($rows) < 2) return 'Add at least two periods to generate a comparison.';

        $sentences = [];
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $pA = $periods[$i];
            $pB = $periods[$i + 1];
            [$cmpA, $cmpB, $wasAligned] = $this->alignForComparison($pA, $pB);

            $aggA = $this->periodAgg($companyId, $cmpA);
            $aggB = $this->periodAgg($companyId, $cmpB);

            $change = $aggB['net_sales'] - $aggA['net_sales'];
            $pct = $aggA['net_sales'] > 0 ? round(abs($change) / $aggA['net_sales'] * 100, 1) : null;
            $direction = $change >= 0 ? 'grew' : 'declined';
            $pctText = $pct !== null ? "{$pct}%" : 'materially';

            $prefix = $wasAligned ? 'Comparing the same calendar months for both periods — ' : '';
            $txt = "{$prefix}Net sales {$direction} {$pctText} from {$cmpA['label']} (" . $this->fmtM($aggA['net_sales'])
                 . ") to {$cmpB['label']} (" . $this->fmtM($aggB['net_sales']) . ').';

            $txnChange  = $aggB['transactions'] - $aggA['transactions'];
            $custChange = $aggB['customers'] - $aggA['customers'];
            $sameDirection = ($change >= 0) === ($txnChange >= 0) && ($change >= 0) === ($custChange >= 0);

            $txt .= $sameDirection
                ? ' Transactions and active customers moved the same direction, suggesting a broad-based shift rather than a handful of one-off deals.'
                : ' Transactions and active customers did not move consistently with net sales here — worth checking whether pricing or product mix, not volume, is driving this.';

            $sentences[] = $txt;
        }

        return implode(' ', $sentences);
    }

    // ── Hero cards: one per consecutive period pair ──────────────
    // Shows both the raw % change AND the day-normalized (daily average)
    // % change, and flags when the two periods aren't the same length,
    // so a full year is never silently compared against a partial one.

    private function computeHeroPairs($companyId, array $periods): array
    {
        $pairs = [];
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $pA = $periods[$i];
            $pB = $periods[$i + 1];
            [$cmpA, $cmpB, $wasAligned] = $this->alignForComparison($pA, $pB);

            $salesA = (float) (SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$cmpA['from'], $cmpA['to']])->sum('net_sales_value'));
            $salesB = (float) (SalesData::where('portfolio_company_id', $companyId)
                ->whereBetween('date', [$cmpB['from'], $cmpB['to']])->sum('net_sales_value'));

            $change = $salesB - $salesA;
            $pct = $salesA > 0 ? round($change / $salesA * 100, 1) : null;

            $pairs[] = [
                'label_a'     => $cmpA['label'], 'label_b' => $cmpB['label'],
                'was_aligned' => $wasAligned,
                'net_sales_a' => $salesA, 'net_sales_b' => $salesB,
                'raw_change'  => $change, 'raw_pct'     => $pct,
            ];
        }
        return $pairs;
    }

    // ── Key Takeaways — synthesized from Zoom In + Vanishing Stars ──

    private function computeTakeaways(array $zoomIn, array $vanishing): array
    {
        // Build the two candidate lists SEPARATELY, then interleave them
        // when assembling the final grid below. An earlier version added
        // every Zoom In item before any Vanishing Stars item — with 3+
        // periods the Zoom In items alone could fill the whole card
        // limit, so a Vanishing Stars warning (often the single most
        // important thing on the page) could be silently dropped no
        // matter how large it was. Interleaving guarantees each source
        // gets a fair shot at a slot.
        $zoomItems = [];
        foreach ($zoomIn as $pair) {
            $natA = $pair['customer_nature_a'];
            $natB = $pair['customer_nature_b'];
            $retChange = $natB['retention_rate'] - $natA['retention_rate'];
            if (abs($retChange) >= 0.1) {
                $direction = $retChange >= 0 ? 'improved' : 'dropped';
                $zoomItems[] = [
                    'tone' => $retChange >= 0 ? 'green' : 'red',
                    'stat' => $natA['retention_rate'] . '% → ' . $natB['retention_rate'] . '%',
                    'text' => "customer retention rate {$direction} from {$pair['period_a']['label']} to {$pair['period_b']['label']} (a " . round(abs($retChange), 1) . '-point change).',
                ];
            }

            if (count($pair['category_breakdown'])) {
                $topCat   = $pair['category_breakdown'][0];
                $totalAbs = collect($pair['category_breakdown'])->sum(fn($r) => abs($r['change']));
                $catPct   = $totalAbs > 0 ? round(abs($topCat['change']) / $totalAbs * 100, 1) : 0;
                $zoomItems[] = [
                    'tone' => $topCat['change'] >= 0 ? 'green' : 'red',
                    'stat' => "{$catPct}%",
                    'text' => "of {$pair['period_a']['label']} → {$pair['period_b']['label']} category movement sits in \"{$topCat['label']}\" alone (" . $this->fmtM($topCat['change']) . ').',
                ];
            }
        }

        $vanishItems = [];
        foreach ($vanishing as $pair) {
            if ($pair['products_count'] > 0) {
                $vanishItems[] = [
                    'tone' => 'amber',
                    'stat' => $this->fmtM($pair['products_total']),
                    'text' => "in {$pair['period_a']['label']} product revenue ({$pair['products_count']} SKUs) vanished by {$pair['period_b']['label']}.",
                ];
            }
            if ($pair['customers_count'] > 0) {
                $vanishItems[] = [
                    'tone' => 'amber',
                    'stat' => $this->fmtM($pair['customers_total']),
                    'text' => "in {$pair['period_a']['label']} customer revenue ({$pair['customers_count']} accounts) vanished by {$pair['period_b']['label']}.",
                ];
            }
        }

        // Interleave: one item from each list in turn.
        $items = [];
        $max = max(count($zoomItems), count($vanishItems));
        for ($i = 0; $i < $max; $i++) {
            if (isset($zoomItems[$i]))   $items[] = $zoomItems[$i];
            if (isset($vanishItems[$i])) $items[] = $vanishItems[$i];
        }

        $items = array_slice($items, 0, 10); // keep the grid readable even with many periods
        foreach ($items as $i => &$item) {
            $item['key'] = "takeaway_{$i}"; // stable per-card key, so editing one card never touches the others
        }
        unset($item);
        return $items;
    }

    private function narrativeZoomIn(array $pair): string
    {
        $natA = $pair['customer_nature_a'];
        $natB = $pair['customer_nature_b'];
        $labelA = $pair['period_a']['label'];
        $labelB = $pair['period_b']['label'];

        $retChange = $natB['retention_rate'] - $natA['retention_rate'];
        $retDir = $retChange >= 0 ? 'improved' : 'worsened';

        $txt = "Customer retention {$retDir} from {$natA['retention_rate']}% in {$labelA} to {$natB['retention_rate']}% in {$labelB}. ";
        $txt .= "New customers: {$natA['new_this_year']} → {$natB['new_this_year']}. "
              . "Churn (Dead): {$natA['churn_dead']} → {$natB['churn_dead']}. "
              . "Reactivated: {$natA['reactivated']} → {$natB['reactivated']}.";

        if (count($pair['category_breakdown'])) {
            $topCat = $pair['category_breakdown'][0];
            $totalAbs = collect($pair['category_breakdown'])->sum(fn($r) => abs($r['change']));
            $catPct = $totalAbs > 0 ? round(abs($topCat['change']) / $totalAbs * 100, 1) : 0;
            $catDir = $topCat['change'] >= 0 ? 'grew' : 'declined';
            $txt .= " By category, \"{$topCat['label']}\" moved the most ({$catDir} " . $this->fmtM($topCat['change']) . ", {$catPct}% of all category movement shown).";
        }

        return $txt;
    }

    private function narrativeVanishing(array $pair): string
    {
        $labelA = $pair['compare_period_a']['label'];
        $labelB = $pair['compare_period_b']['label'];
        $thresholdPct = $pair['threshold_pct'];
        $parts = [];

        if ($pair['products_count'] > 0) {
            $top = $pair['products'][0];
            $parts[] = "{$pair['products_count']} product(s) that were meaningful in {$labelA} (at least {$thresholdPct}% of that period's net sales) collapsed to under 5% of that by {$labelB}, representing "
                . $this->fmtM($pair['products_total']) . ' in revenue not repeated — the largest, "' . $top['name'] . '", went from '
                . $this->fmtM($top['value_a']) . ' to ' . $this->fmtM($top['value_b']) . '.';
        } else {
            $parts[] = "No products that were meaningful in {$labelA} collapsed by {$labelB}.";
        }

        if ($pair['customers_count'] > 0) {
            $top = $pair['customers'][0];
            $parts[] = "{$pair['customers_count']} customer(s) show the same pattern, representing " . $this->fmtM($pair['customers_total'])
                . ' not repeated — the largest, "' . $top['name'] . '", went from ' . $this->fmtM($top['value_a']) . ' to ' . $this->fmtM($top['value_b']) . '.';
        } else {
            $parts[] = "No customers that were meaningful in {$labelA} collapsed by {$labelB}.";
        }

        return implode(' ', $parts);
    }

    private function narrativeTopRanked(array $topRanked): string
    {
        $parts = [];
        foreach (['customers' => 'customer', 'products' => 'product'] as $key => $label) {
            $cols = $topRanked[$key] ?? [];
            if (count($cols) < 1) continue;

            $first = $cols[0];
            $last  = $cols[count($cols) - 1];
            $firstNames = collect($first['rows'])->pluck('name');
            $lastNames  = collect($last['rows'])->pluck('name');
            $newEntrants = $lastNames->diff($firstNames)->count();
            $dropped     = $firstNames->diff($lastNames)->count();
            $limit = $last['limit'] ?? 100;

            $parts[] = "For {$label}s, the Top {$limit} in {$last['label']} account for {$last['top_n_share']}% of total net sales, with the Top 10 alone accounting for {$last['top10_share']}%.";
            if (count($cols) > 1) {
                $parts[] = "Compared to {$first['label']}, {$newEntrants} new {$label}(s) entered the Top {$limit} and {$dropped} dropped out.";
            }
        }
        return implode(' ', $parts) ?: 'Add at least two periods to generate a summary.';
    }

    private function narrativeProductConcentration(array $productConcentration): string
    {
        if (empty($productConcentration)) return 'No product data available for this comparison.';

        $latest = end($productConcentration);
        $cats = collect($latest['categories']);
        if ($cats->isEmpty()) return "No categorized product data found in {$latest['period']['label']}.";

        $shareOf = fn($c) => $c['total_products'] > 0 ? $c['core_count'] / $c['total_products'] * 100 : 0;
        $avgProductSharePct = round($cats->map($shareOf)->avg(), 1);
        $mostConcentrated   = $cats->sortBy($shareOf)->first();
        $mostSpread         = $cats->sortByDesc($shareOf)->first();

        return "In {$latest['period']['label']}, an average of just {$avgProductSharePct}% of a category's products account for ~85% of its revenue. "
             . "\"{$mostConcentrated['category']}\" is the most concentrated — only {$mostConcentrated['core_count']} of its {$mostConcentrated['total_products']} products drive the bulk of sales. "
             . "\"{$mostSpread['category']}\" is the most spread out, needing {$mostSpread['core_count']} of {$mostSpread['total_products']} products to reach that same ~85%.";
    }

    private function narrativeDimension(string $label, array $section): string
    {
        $periods = $section['periods'];
        $movements = $section['movements'];
        if (empty($periods)) return "No {$label} data available for this comparison.";

        $latest = end($periods);
        if (empty($latest['rows'])) return "No {$label} data found in {$latest['period']['label']}.";

        $top = collect($latest['rows'])->first();
        $txt = "In {$latest['period']['label']}, \"{$top['label']}\" is the largest {$label} at {$top['pct']}% of net sales (" . $this->fmtM($top['value']) . ').';

        if (!empty($movements)) {
            $lastMove = end($movements);
            if (!empty($lastMove['rows'])) {
                $biggest = $lastMove['rows'][0];
                $dir = $biggest['change'] >= 0 ? 'grew' : 'declined';
                $txt .= " The biggest shift from {$lastMove['period_a']['label']} to {$lastMove['period_b']['label']} was in \"{$biggest['label']}\", which {$dir} by " . $this->fmtM(abs($biggest['change'])) . '.';
            }
        }

        return $txt;
    }

    // ── Product Concentration — per category, per period ──────────
    // Splits each category's products into a "core" group (the fewest,
    // largest products whose combined value reaches ~85% of the
    // category's total) and a "long tail" (the rest). Shows product
    // count and distinct customer count for each group. A customer who
    // buys both a core and a tail product counts in both groups — that's
    // real overlap, not a bug.

    private function computeProductConcentration($companyId, array $period): array
    {
        $rows = SalesData::where('portfolio_company_id', $companyId)
            ->whereBetween('date', [$period['from'], $period['to']])
            ->whereNotNull('product_category')->where('product_category', '!=', '')
            ->whereNotNull('product_item')->where('product_item', '!=', '')
            ->selectRaw('product_category, product_item, customer_name, SUM(net_sales_value) as value')
            ->groupBy('product_category', 'product_item', 'customer_name')
            ->get();

        $result = [];
        foreach ($rows->groupBy('product_category') as $category => $catRows) {
            $productTotals = $catRows->groupBy('product_item')->map(fn($g) => $g->sum('value'))->sortDesc();
            $categoryTotal = $productTotals->sum();

            $cumulative = 0;
            $coreProducts = [];
            foreach ($productTotals as $product => $val) {
                if ($categoryTotal > 0 && $cumulative >= $categoryTotal * 0.85) break;
                $coreProducts[] = $product;
                $cumulative += $val;
            }
            if (empty($coreProducts) && $productTotals->count() > 0) {
                $coreProducts[] = $productTotals->keys()->first();
            }
            $tailProducts = $productTotals->keys()->diff($coreProducts)->values()->all();

            $coreValue = $productTotals->only($coreProducts)->sum();
            $tailValue = $categoryTotal - $coreValue;
            $coreCustomers = $catRows->whereIn('product_item', $coreProducts)->pluck('customer_name')->unique()->count();
            $tailCustomers = $catRows->whereIn('product_item', $tailProducts)->pluck('customer_name')->unique()->count();

            $result[] = [
                'category'        => $category,
                'total_products'  => $productTotals->count(),
                'total_value'     => $categoryTotal,
                'core_count'      => count($coreProducts),
                'core_value'      => $coreValue,
                'core_pct'        => $categoryTotal > 0 ? round($coreValue / $categoryTotal * 100, 1) : 0,
                'core_customers'  => $coreCustomers,
                'tail_count'      => count($tailProducts),
                'tail_value'      => $tailValue,
                'tail_pct'        => $categoryTotal > 0 ? round($tailValue / $categoryTotal * 100, 1) : 0,
                'tail_customers'  => $tailCustomers,
            ];
        }

        usort($result, fn($a, $b) => $b['total_value'] <=> $a['total_value']);
        return $result;
    }
}
