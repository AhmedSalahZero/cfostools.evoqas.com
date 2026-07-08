<?php

namespace App\Http\Controllers;

use App\Models\StudyOpeningBalance;
use App\Models\PortfolioCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OpeningBalanceController extends Controller
{
    private function authorizeStudyCompany(int $companyId, int $studyId): void
    {
        $this->authorizeCompany($companyId, 'financial_studies');
        abort_unless(
            DB::table('financial_studies')
                ->where('id', $studyId)
                ->where('portfolio_company_id', $companyId)
                ->exists(),
            404
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    //  GET  /portfolio-companies/{companyId}/financial-studies/{studyId}/opening-balance
    //  Show the Opening Balance step page
    // ─────────────────────────────────────────────────────────────────────
    public function show(int $companyId, int $studyId)
    {
        $this->authorizeStudyCompany($companyId, $studyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')->where('id', $studyId)->firstOrFail();

        // ── Study currency ────────────────────────────────────────────────
        $studyCurrency = $study->study_currency ?? 'USD';

        // ── General assumptions (includes new_company flag + raw_materials) ─
        $generalAssumptions = json_decode($study->general_assumptions ?? '{}', true) ?? [];

        // ── Step 1 — Products ─────────────────────────────────────────────
        $products = json_decode($study->products ?? '[]', true) ?? [];

        // ── Step 2 — Sales Projections (FG beginning inventory) ───────────
        $projections = json_decode($study->projections ?? '[]', true) ?? [];

        // ── Step 3 — COGS data (trading & RM beginning inventory) ─────────
        $cogsData = json_decode($study->cogs_data ?? '[]', true) ?? [];

        // ── Study timeline → month labels for the settlement modal ─────────
        $studyMonths = $this->buildStudyMonths($study);

        // ── Previously saved opening balance (from our dedicated table) ───
        $saved = StudyOpeningBalance::where('financial_study_id', $studyId)->first();
        $savedData = $saved ? $saved->toArray() : null;

        return Inertia::render('FinancialStudies/OpeningBalanceStep', [
            'company' => [
                'id'       => $company->id,
                'name'     => $company->name,
                'currency' => $studyCurrency,
            ],
            'study' => [
                'id'             => $study->id,
                'name'           => $study->name,
                'start_date'     => $study->study_start_date,
                'duration_years' => $study->duration_years,
            ],
            'products'           => $products,
            'projections'        => $projections,
            'cogsData'           => $cogsData,
            'generalAssumptions' => $generalAssumptions,
            'studyMonths'        => $studyMonths,
            'savedData'          => $savedData,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  POST  /portfolio-companies/{companyId}/financial-studies/{studyId}/opening-balance
    //  Save (upsert) the opening balance
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request, int $companyId, int $studyId)
    {
        $this->authorizeStudyCompany($companyId, $studyId);
        // Verify study belongs to company
        $study = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $request->validate([
            'as_of_date'             => 'nullable|date',
            'notes'                  => 'nullable|string|max:500',
            // Dedicated scalar fields
            'cash_bank'              => 'nullable|numeric|min:0',
            'paid_up_capital'        => 'nullable|numeric|min:0',
            'legal_reserve'          => 'nullable|numeric|min:0',
            'retained_earnings'      => 'nullable|numeric',
            // Array sections
            'fixed_assets'           => 'nullable|array',
            'inventory'              => 'nullable|array',
            'current_assets'         => 'nullable|array',
            'other_non_current'      => 'nullable|array',
            'long_term_liabilities'  => 'nullable|array',
            'current_liabilities'    => 'nullable|array',
            'equity'                 => 'nullable|array',
        ]);

        // Upsert — one record per study
        $ob = StudyOpeningBalance::firstOrNew(['financial_study_id' => $studyId]);

        $ob->financial_study_id     = $studyId;
        $ob->as_of_date             = $request->as_of_date ?: null;
        $ob->notes                  = $request->notes ?: null;
        // Scalar equity / cash fields
        $ob->cash_bank              = (float) ($request->cash_bank         ?? 0);
        $ob->paid_up_capital        = (float) ($request->paid_up_capital   ?? 0);
        $ob->legal_reserve          = (float) ($request->legal_reserve     ?? 0);
        $ob->retained_earnings      = (float) ($request->retained_earnings ?? 0);
        // Array sections
        $ob->fixed_assets           = $request->fixed_assets           ?? [];
        $ob->inventory              = $request->inventory              ?? [];
        $ob->current_assets         = $request->current_assets         ?? [];
        $ob->other_non_current      = $request->other_non_current      ?? [];
        $ob->long_term_liabilities  = $request->long_term_liabilities  ?? [];
        $ob->current_liabilities    = $request->current_liabilities    ?? [];
        $ob->equity                 = $request->equity                 ?? [];

        // Compute and store all decimal totals + is_balanced flag
        $ob->computeTotals();
        $ob->save();

        // Also keep the financial_studies.opening_balance JSON column in sync
        // so the Results Engine (which reads that column) still works
        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'opening_balance' => json_encode([
                    'source'                 => 'manual',
                    'as_of_date'             => $ob->as_of_date?->format('Y-m-d'),
                    'notes'                  => $ob->notes,
                    // Dedicated scalar fields — read directly by engine
                    'cash_bank'              => (float) $ob->cash_bank,
                    'paid_up_capital'        => (float) $ob->paid_up_capital,
                    'legal_reserve'          => (float) $ob->legal_reserve,
                    'retained_earnings'      => (float) $ob->retained_earnings,
                    'fixed_assets'           => $ob->fixed_assets,
                    'inventory'              => $ob->inventory,
                    'current_assets'         => $ob->current_assets,
                    'other_non_current'      => $ob->other_non_current,
                    'long_term_liabilities'  => $ob->long_term_liabilities,
                    'current_liabilities'    => $ob->current_liabilities,
                    'equity'                 => $ob->equity,
                    'totals'                 => [
                        'gross_fa'                  => (float) $ob->total_gross_fa,
                        'accum_dep'                 => (float) $ob->total_accum_dep,
                        'net_fa'                    => (float) $ob->total_net_fa,
                        'inventory'                 => (float) $ob->total_inventory,
                        'current_assets'            => (float) $ob->total_current_assets,
                        'other_non_current'         => (float) $ob->total_other_non_current,
                        'long_term_liabilities'     => (float) $ob->total_long_term_liabilities,
                        'current_liabilities'       => (float) $ob->total_current_liabilities,
                        'equity'                    => (float) $ob->total_equity,
                        'total_assets'              => (float) $ob->total_assets,
                        'total_liabilities'         => (float) $ob->total_liabilities,
                    ],
                    'is_balanced' => $ob->is_balanced,
                ]),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success'  => true,
            'redirect' => route('financial-studies.results', [$companyId, $studyId]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Private helper — build month label array for the settlement modal
    // ─────────────────────────────────────────────────────────────────────
    private function buildStudyMonths(object $study): array
    {
        $months = [];
        if (!$study->study_start_date) return $months;

        $durationYears = (int) ($study->duration_years ?? 3);
        $totalMonths   = $durationYears * 12;
        $current       = Carbon::parse($study->study_start_date)->startOfMonth();

        for ($i = 0; $i < $totalMonths; $i++) {
            $months[] = $current->format('M Y');   // "Jan 2026"
            $current->addMonth();
        }

        return $months;
    }
}