<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PortfolioCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class InvestorDecisionController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // INDEX — Tool landing page (org-level)
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user  = Auth::user();
        $orgId = $this->resolveOrgId($user, $request);

        // All prospects for this org
        $prospects = PortfolioCompany::where('organization_id', $orgId)
            ->where('type', 'prospect')
            ->orderBy('name')
            ->get(['id', 'name', 'sector', 'status', 'invested_amount',
                   'invested_currency', 'entry_valuation']);

        // Saved evaluations (from investor_evaluations table if it exists, else empty)
        $evaluations = [];
        if ($this->tableExists('investor_evaluations')) {
            $evaluations = DB::table('investor_evaluations')
                ->where('organization_id', $orgId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        }

        return Inertia::render('InvestorDecision/Index', [
            'prospects'   => $prospects,
            'evaluations' => $evaluations,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EVALUATE — Single prospect deep-dive
    // ─────────────────────────────────────────────────────────────────────────
    public function evaluate(Request $request, $companyId)
    {
        $user    = Auth::user();
        $orgId   = $this->resolveOrgId($user, $request);
        $company = PortfolioCompany::where('organization_id', $orgId)
            ->findOrFail($companyId);

        $financials  = $this->gatherFinancials($companyId);
        $salesData   = $this->gatherSalesData($companyId);
        $budgetData  = $this->gatherBudgetData($companyId);
        $kpiData     = $this->gatherKpiData($companyId);
        $studyData   = $this->gatherStudyData($companyId);

        // All prospects for switcher
        $prospects = PortfolioCompany::where('organization_id', $orgId)
            ->where('type', 'prospect')
            ->orderBy('name')
            ->get(['id', 'name', 'sector']);

        // Load previously saved evaluation
        $savedEvaluation = null;
        if ($this->tableExists('investor_evaluations')) {
            $row = DB::table('investor_evaluations')
                ->where('organization_id', $orgId)
                ->where('portfolio_company_id', $companyId)
                ->first();
            if ($row) {
                $savedEvaluation = [
                    'scores'     => json_decode($row->scores     ?? '{}', true) ?? [],
                    'notes'      => $row->notes   ?? '',
                    'verdict'    => $row->verdict  ?? '',
                    'thresholds' => json_decode($row->thresholds ?? '{}', true) ?? [],
                ];
            }
        }

        return Inertia::render('InvestorDecision/Evaluate', [
            'company'         => [
                'id'               => $company->id,
                'name'             => $company->name,
                'sector'           => $company->sector,
                'status'           => $company->status,
                'invested_amount'  => (float) $company->invested_amount,
                'invested_currency'=> $company->invested_currency,
                'entry_valuation'  => (float) $company->entry_valuation,
                'current_valuation'=> $company->current_valuation ? (float) $company->current_valuation : null,
                'moic'             => $company->moic ? (float) $company->moic : null,
                'irr'              => $company->irr  ? (float) $company->irr  : null,
                'equity_stake'     => (float) $company->equity_stake,
                'logo'             => null,
                'lead_source'      => $company->lead_source,
                'transaction_date' => $company->transaction_date?->format('Y-m-d'),
            ],
            'financials'      => $financials,
            'salesData'       => $salesData,
            'budgetData'      => $budgetData,
            'kpiData'         => $kpiData,
            'studyData'       => $studyData,
            'prospects'       => $prospects,
            'savedEvaluation' => $savedEvaluation,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COMPARE — Head-to-head two prospects
    // ─────────────────────────────────────────────────────────────────────────
    public function compare(Request $request)
    {
        $user   = Auth::user();
        $orgId  = $this->resolveOrgId($user, $request);

        $idA = $request->query('a');
        $idB = $request->query('b');

        $prospects = PortfolioCompany::where('organization_id', $orgId)
            ->where('type', 'prospect')
            ->orderBy('name')
            ->get(['id', 'name', 'sector', 'status', 'invested_amount',
                   'invested_currency', 'entry_valuation',
                   'moic', 'irr', 'equity_stake', 'current_valuation', 'lead_source']);

        $companyA = null;
        $companyB = null;

        if ($idA) {
            $cA = PortfolioCompany::where('organization_id', $orgId)->find($idA);
            if ($cA) {
                $companyA = $this->buildCompanyPayload($cA);
            }
        }

        if ($idB) {
            $cB = PortfolioCompany::where('organization_id', $orgId)->find($idB);
            if ($cB) {
                $companyB = $this->buildCompanyPayload($cB);
            }
        }

        return Inertia::render('InvestorDecision/Compare', [
            'prospects' => $prospects,
            'companyA'  => $companyA,
            'companyB'  => $companyB,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE EVALUATION — Store manual scores & notes (JSON)
    // ─────────────────────────────────────────────────────────────────────────
    public function saveEvaluation(Request $request, $companyId)
    {
        $user  = Auth::user();
        $orgId = $this->resolveOrgId($user, $request);

        $validated = $request->validate([
            'scores'      => 'nullable|array',
            'notes'       => 'nullable|string|max:10000',
            'verdict'     => 'nullable|in:strong_buy,buy,hold,pass,strong_pass',
            'saved_name'  => 'nullable|string|max:200',
            'thresholds'  => 'nullable|array',
        ]);

        $company = PortfolioCompany::where('organization_id', $orgId)->findOrFail($companyId);

        // Auto-create table if migration hasn't been run yet
        if (!$this->tableExists('investor_evaluations')) {
            \Illuminate\Support\Facades\Schema::create('investor_evaluations', function ($table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('portfolio_company_id');
                $table->string('company_name');
                $table->string('saved_name')->nullable();
                $table->json('scores')->nullable();
                $table->json('thresholds')->nullable();
                $table->text('notes')->nullable();
                $table->string('verdict', 30)->nullable();
                $table->timestamps();
            });
        }

        $existing = DB::table('investor_evaluations')
            ->where('organization_id', $orgId)
            ->where('portfolio_company_id', $companyId)
            ->first();

        $payload = [
            'organization_id'      => $orgId,
            'portfolio_company_id' => $companyId,
            'company_name'         => $company->name,
            'scores'               => json_encode($validated['scores'] ?? []),
            'notes'                => $validated['notes'] ?? '',
            'verdict'              => $validated['verdict'] ?? null,
            'saved_name'           => $validated['saved_name'] ?? $company->name,
            'thresholds'           => json_encode($validated['thresholds'] ?? []),
            'updated_at'           => now(),
        ];

        if ($existing) {
            DB::table('investor_evaluations')->where('id', $existing->id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('investor_evaluations')->insertGetId($payload);
        }

        return redirect()->back()->with('success', 'Evaluation saved successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function buildCompanyPayload($company): array
    {
        $id = $company->id;
        return [
            'id'                => $company->id,
            'name'              => $company->name,
            'sector'            => $company->sector,
            'status'            => $company->status,
            'invested_amount'   => (float) $company->invested_amount,
            'invested_currency' => $company->invested_currency,
            'entry_valuation'   => (float) $company->entry_valuation,
            'current_valuation' => $company->current_valuation ? (float) $company->current_valuation : null,
            'moic'              => $company->moic ? (float) $company->moic : null,
            'irr'               => $company->irr  ? (float) $company->irr  : null,
            'equity_stake'      => (float) $company->equity_stake,
            'logo'              => null,
            'lead_source'       => $company->lead_source,
            'financials'        => $this->gatherFinancials($id),
            'salesData'         => $this->gatherSalesData($id),
            'budgetData'        => $this->gatherBudgetData($id),
            'kpiData'           => $this->gatherKpiData($id),
            'studyData'         => $this->gatherStudyData($id),
        ];
    }

    private function gatherFinancials($companyId): array
    {
        // Latest financial statement
        $stmt = DB::table('financial_statements')
            ->where('portfolio_company_id', $companyId)
            ->where('status', 'final')
            ->orderBy('period_to', 'desc')
            ->first();

        if (!$stmt) {
            return ['has_data' => false];
        }

        $sections = DB::table('fs_sections')
            ->where('financial_statement_id', $stmt->id)
            ->get();

        $totals = [];
        foreach ($sections as $sec) {
            if (!$sec->is_computed) {
                $totals[$sec->section_key] = DB::table('fs_line_items')
                    ->where('fs_section_id', $sec->id)
                    ->sum('amount');
            }
        }

        // Propagate computed sections
        for ($pass = 0; $pass < 10; $pass++) {
            foreach ($sections as $sec) {
                if (!$sec->is_computed || !$sec->computed_from) continue;
                $formula = is_string($sec->computed_from)
                    ? json_decode($sec->computed_from, true)
                    : $sec->computed_from;
                if (!is_array($formula)) continue;
                $val = 0;
                foreach ($formula as $item) {
                    if (($item['sign'] ?? 0) == 1)  $val += $totals[$item['key']] ?? 0;
                    if (($item['sign'] ?? 0) == -1) $val -= $totals[$item['key']] ?? 0;
                }
                $totals[$sec->section_key] = $val;
            }
        }

        $revenue   = $totals['sales_revenue']    ?? 0;
        $cogs      = $totals['cogs']              ?? 0;
        $grossProfit = $totals['gross_profit']    ?? ($revenue - $cogs);
        $ebitda    = $totals['ebitda']            ?? 0;
        $netProfit = $totals['net_profit']        ?? 0;
        $totalAssets = ($totals['non_current_assets'] ?? 0) + ($totals['current_assets'] ?? 0);
        $totalDebt = $totals['long_term_liabilities'] ?? 0;
        $equity    = $totals['equity']            ?? 0;

        // Ratios
        $grossMargin  = $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : null;
        $ebitdaMargin = $revenue > 0 ? round($ebitda / $revenue * 100, 1) : null;
        $netMargin    = $revenue > 0 ? round($netProfit / $revenue * 100, 1) : null;
        $debtToEquity = $equity > 0 ? round($totalDebt / $equity, 2) : null;
        $roe          = $equity > 0 ? round($netProfit / $equity * 100, 1) : null;
        $roa          = $totalAssets > 0 ? round($netProfit / $totalAssets * 100, 1) : null;

        // Trend: last 3 final statements
        $trend = DB::table('financial_statements')
            ->where('portfolio_company_id', $companyId)
            ->where('status', 'final')
            ->orderBy('period_to', 'desc')
            ->limit(3)
            ->pluck('period_to', 'id')
            ->toArray();

        $trendData = [];
        foreach (array_reverse($trend, true) as $sid => $pdate) {
            $secs = DB::table('fs_sections')->where('financial_statement_id', $sid)->get();
            $t = [];
            foreach ($secs as $s) {
                if (!$s->is_computed) {
                    $t[$s->section_key] = DB::table('fs_line_items')
                        ->where('fs_section_id', $s->id)->sum('amount');
                }
            }
            for ($p = 0; $p < 5; $p++) {
                foreach ($secs as $s) {
                    if (!$s->is_computed || !$s->computed_from) continue;
                    $f2 = is_string($s->computed_from) ? json_decode($s->computed_from, true) : $s->computed_from;
                    if (!is_array($f2)) continue;
                    $v = 0;
                    foreach ($f2 as $item) {
                        if (($item['sign'] ?? 0) == 1)  $v += $t[$item['key']] ?? 0;
                        if (($item['sign'] ?? 0) == -1) $v -= $t[$item['key']] ?? 0;
                    }
                    $t[$s->section_key] = $v;
                }
            }
            $rev = $t['sales_revenue'] ?? 0;
            $trendData[] = [
                'period'     => substr($pdate, 0, 7),
                'revenue'    => round($rev),
                'gross_profit' => round($t['gross_profit'] ?? ($rev - ($t['cogs'] ?? 0))),
                'ebitda'     => round($t['ebitda'] ?? 0),
                'net_profit' => round($t['net_profit'] ?? 0),
            ];
        }

        return [
            'has_data'      => true,
            'period_to'     => $stmt->period_to,
            'currency'      => $stmt->currency ?? 'EGP',
            'revenue'       => round($revenue),
            'cogs'          => round($cogs),
            'gross_profit'  => round($grossProfit),
            'ebitda'        => round($ebitda),
            'net_profit'    => round($netProfit),
            'total_assets'  => round($totalAssets),
            'total_debt'    => round($totalDebt),
            'equity'        => round($equity),
            'gross_margin'  => $grossMargin,
            'ebitda_margin' => $ebitdaMargin,
            'net_margin'    => $netMargin,
            'debt_to_equity'=> $debtToEquity,
            'roe'           => $roe,
            'roa'           => $roa,
            'trend'         => $trendData,
        ];
    }

    private function gatherSalesData($companyId): array
    {
        $rows = DB::table('sales_data')
            ->where('portfolio_company_id', $companyId)
            ->selectRaw('
                SUM(net_sales_value) as total_revenue,
                COUNT(DISTINCT customer_name) as customer_count,
                COUNT(DISTINCT product_item) as product_count,
                COUNT(DISTINCT DATE_FORMAT(date,"%Y-%m")) as months_active,
                MAX(date) as last_date,
                MIN(date) as first_date
            ')
            ->first();

        if (!$rows || !$rows->total_revenue) {
            return ['has_data' => false];
        }

        $monthlyData = DB::table('sales_data')
            ->where('portfolio_company_id', $companyId)
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as month, SUM(net_sales_value) as rev')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        // Revenue growth: full calendar year totals — simple and accurate
        // (23.7M - 20.1M) / 20.1M = 17.9%
        $yearTotals = DB::table('sales_data')
            ->where('portfolio_company_id', $companyId)
            ->selectRaw('YEAR(date) as yr, SUM(net_sales_value) as total')
            ->groupBy('yr')
            ->orderBy('yr')
            ->get()
            ->values();

        $growth      = null;
        $growthBasis = 'no data';

        if ($yearTotals->count() >= 2) {
            $last = $yearTotals[$yearTotals->count() - 1];
            $prev = $yearTotals[$yearTotals->count() - 2];

            if ((float)$prev->total > 0) {
                $growth      = round(((float)$last->total - (float)$prev->total) / (float)$prev->total * 100, 1);
                $growthBasis = $prev->yr . ' vs ' . $last->yr;
            }
        } elseif ($yearTotals->count() === 1) {
            $growthBasis = 'Only 1 year of data';
        }

        // Limit to last 24 months for the sparkline chart
        $monthlyData = array_slice($monthlyData, -24);

        $topProducts = DB::table('sales_data')
            ->where('portfolio_company_id', $companyId)
            ->selectRaw('product_item, SUM(net_sales_value) as total')
            ->groupBy('product_item')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'has_data'       => true,
            'total_revenue'  => round($rows->total_revenue),
            'customer_count' => (int) $rows->customer_count,
            'product_count'  => (int) $rows->product_count,
            'months_active'  => (int) $rows->months_active,
            'revenue_growth' => $growth,
            'growth_basis'   => $growthBasis,
            'monthly_trend'  => array_map(fn($r) => ['month' => $r->month, 'rev' => round($r->rev)], $monthlyData),
            'top_products'   => array_map(fn($r) => ['name' => $r->product_item, 'value' => round($r->total)], $topProducts),
        ];
    }

    private function gatherBudgetData($companyId): array
    {
        if (!$this->tableExists('budget_statements')) {
            return ['has_data' => false];
        }

        $budget = DB::table('budget_statements')
            ->where('portfolio_company_id', $companyId)
            ->where('year', date('Y'))
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$budget) {
            return ['has_data' => false];
        }

        $currentMonth = (int) date('n');

        // Revenue budget vs actuals YTD
        $revSection = DB::table('budget_sections')
            ->where('budget_statement_id', $budget->id)
            ->where('statement_type', 'income')
            ->where('section_key', 'sales_revenue')
            ->first();

        $budgetRev = 0;
        $actualRev = 0;

        if ($revSection) {
            $lineItems = DB::table('budget_line_items')
                ->where('budget_section_id', $revSection->id)
                ->get();
            foreach ($lineItems as $li) {
                $monthly = json_decode($li->monthly_amounts ?? '{}', true) ?? [];
                for ($m = 1; $m <= $currentMonth; $m++) {
                    $budgetRev += (float)($monthly[$m] ?? 0);
                }
            }

            $actuals = DB::table('budget_actuals')
                ->join('budget_line_items', 'budget_actuals.budget_line_item_id', '=', 'budget_line_items.id')
                ->where('budget_line_items.budget_section_id', $revSection->id)
                ->where('budget_actuals.month', '<=', $currentMonth)
                ->sum('budget_actuals.amount');
            $actualRev = (float)$actuals;
        }

        $variance    = $actualRev - $budgetRev;
        $variancePct = $budgetRev > 0 ? round($variance / $budgetRev * 100, 1) : null;

        return [
            'has_data'         => true,
            'year'             => $budget->year,
            'budget_revenue'   => round($budgetRev),
            'actual_revenue'   => round($actualRev),
            'variance'         => round($variance),
            'variance_pct'     => $variancePct,
        ];
    }

    private function gatherKpiData($companyId): array
    {
        $kpis = DB::table('kpi_trackings as kt')
            ->join('kpi_definitions as kd', 'kd.id', '=', 'kt.kpi_definition_id')
            ->where('kt.company_id', $companyId)
            ->orderBy('kt.created_at', 'desc')
            ->limit(20)
            ->select('kd.name', 'kd.higher_is_better', 'kt.actual', 'kt.target', 'kt.period_label')
            ->get();

        if ($kpis->isEmpty()) {
            return ['has_data' => false];
        }

        $onTrack = 0;
        $atRisk  = 0;
        $watch   = 0;
        $items   = [];

        foreach ($kpis as $k) {
            $ratio = ($k->target != 0) ? $k->actual / $k->target : null;
            if ($ratio === null) { $status = 'watch'; }
            elseif ($k->higher_is_better) {
                $status = $ratio >= 0.95 ? 'on_track' : ($ratio >= 0.75 ? 'watch' : 'at_risk');
            } else {
                $status = $ratio <= 1.05 ? 'on_track' : ($ratio <= 1.25 ? 'watch' : 'at_risk');
            }
            if ($status === 'on_track') $onTrack++;
            elseif ($status === 'at_risk') $atRisk++;
            else $watch++;

            $items[] = [
                'name'   => $k->name,
                'actual' => (float)$k->actual,
                'target' => (float)$k->target,
                'status' => $status,
                'period' => $k->period_label,
            ];
        }

        return [
            'has_data'     => true,
            'total'        => count($items),
            'on_track'     => $onTrack,
            'at_risk'      => $atRisk,
            'watch'        => $watch,
            'health_score' => count($items) > 0 ? round($onTrack / count($items) * 100) : null,
            'items'        => array_slice($items, 0, 5),
        ];
    }

    private function gatherStudyData($companyId): array
    {
        if (!$this->tableExists('financial_studies')) {
            return ['has_data' => false];
        }

        $study = DB::table('financial_studies')
            ->where('portfolio_company_id', $companyId)
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$study) {
            return ['has_data' => false];
        }

        // Pull key summary metrics from the study's results if stored
        $general = json_decode($study->general_assumptions ?? '{}', true) ?? [];
        $projections = json_decode($study->projections ?? '{}', true) ?? [];

        $studyCurrency = $general['study_currency'] ?? 'EGP';
        $yearsCount    = (int)($general['years'] ?? 3);

        // Try to get NPV/IRR from results if the study has been run
        $npv   = $projections['npv']  ?? null;
        $irr   = $projections['irr']  ?? null;
        $moic  = $projections['moic'] ?? null;

        return [
            'has_data'      => true,
            'study_name'    => $study->name ?? 'Financial Study',
            'study_currency'=> $studyCurrency,
            'years'         => $yearsCount,
            'npv'           => $npv ? round($npv) : null,
            'irr'           => $irr ? round($irr, 1) : null,
            'moic'          => $moic ? round($moic, 2) : null,
            'updated_at'    => $study->updated_at,
        ];
    }

    private function resolveOrgId($user, $request = null): int|null
    {
        // super-admin: use org_id from query param, or their own org, or first org
        if ($user->hasRole('super-admin')) {
            if ($request && $request->has('org_id')) {
                return (int) $request->query('org_id');
            }
            if ($user->organization_id) {
                return $user->organization_id;
            }
            // Fall back to the first organization
            $first = DB::table('organizations')->orderBy('id')->value('id');
            return $first;
        }
        return $user->organization_id;
    }

    private function tableExists(string $table): bool
    {
        try {
            DB::table($table)->limit(1)->get();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}