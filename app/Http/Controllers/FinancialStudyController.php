<?php

namespace App\Http\Controllers;

use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FinancialStudyController extends Controller
{
    private function authorizeStudy(int $companyId): PortfolioCompany
    {
        return $this->authorizeCompany($companyId, 'financial_studies');
    }

    // ─────────────────────────────────────────────
    //  INDEX  — list all studies for a company
    // ─────────────────────────────────────────────
    public function index($companyId)
    {
        $company = $this->authorizeStudy((int) $companyId);

        $studies = DB::table('financial_studies')
            ->where('portfolio_company_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($s) {
                return [
                    'id'                 => $s->id,
                    'name'               => $s->name,
                    'study_currency'     => $s->study_currency,
                    'study_start_date'   => $s->study_start_date,
                    'study_end_date'     => $s->study_end_date,
                    'duration_years'     => $s->duration_years,
                    'business_type'      => $s->business_type,
                    'business_sector'    => $s->business_sector,
                    'created_at'         => $s->created_at,
                    'has_sales'           => !empty($s->projections)       && $s->projections       !== '{}' && $s->projections       !== '[]',
                    'has_cogs'            => !empty($s->cogs_data)         && $s->cogs_data         !== '[]',
                    'has_manpower'        => !empty($s->manpower_data)     && $s->manpower_data     !== '[]',
                    'has_expenses'        => !empty($s->expenses_data)     && $s->expenses_data     !== '[]',
                    'has_fixed_assets'    => !empty($s->fixed_assets_data) && $s->fixed_assets_data !== '[]',
                    'has_opening_balance' => !empty($s->opening_balance)   && $s->opening_balance   !== 'null',
                    'has_results'         => !empty($s->writeups)          && str_contains((string)($s->writeups ?? ''), '"results"'),
                ];
            });

        return Inertia::render('FinancialStudies/Index', [
            'company' => [
                'id'   => $company->id,
                'name' => $company->name,
            ],
            'studies' => $studies,
        ]);
    }

    // ─────────────────────────────────────────────
    //  CREATE  — show blank form
    // ─────────────────────────────────────────────
    public function create($companyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();

        return Inertia::render('FinancialStudies/Create', [
            'company'    => ['id' => $company->id, 'name' => $company->name],
            'study'      => null,
            'currencies' => $this->currencies(),
            'measurementUnits' => $this->measurementUnits(),
            'inventoryCoverageDays' => $this->inventoryCoverageDays(),
        ]);
    }

    // ─────────────────────────────────────────────
    //  STORE  — save new study (Step 1 only)
    // ─────────────────────────────────────────────
    public function store(Request $request, $companyId)
    {
        $this->authorizeStudy((int) $companyId);
        $request->validate([
            'name'               => 'required|string|max:255',
            'study_currency'     => 'required|string',
            'start_date'         => 'required|string',
            'duration_years'     => 'required|integer|min:1|max:20',
            'operation_start_date' => 'nullable|string',
            'new_company'        => 'nullable|boolean',
            'corporate_tax_rate' => 'required|numeric|min:0|max:100',
            'required_investment_return_pct' => $request->duration_years > 2 ? 'required|numeric|min:0|max:100' : 'nullable|numeric|min:0|max:100',
            'perpetual_growth_rate_pct'      => $request->duration_years > 2 ? 'required|numeric|min:0|max:20'  : 'nullable|numeric|min:0|max:20',
            'products'           => 'required|array|min:1',
            'products.*.name'    => 'required|string|max:255',
            'products.*.nature'  => 'required|in:manufacturing,trading,service',
            'products.*.measurement_unit' => 'nullable|string',
            'products.*.selling_start_date' => 'nullable|string',
            'products.*.vat_rate' => 'nullable|numeric',
            'products.*.withhold_tax_rate' => 'nullable|numeric',
            'raw_materials'      => 'nullable|array',
            'raw_materials.*.name' => 'nullable|string|max:255',
        ]);
        $natures = collect($request->products)->pluck('nature')->unique()->values()->toArray();
        $businessType = count($natures) === 1 ? $natures[0] : 'mixed';

        // Parse start date "YYYY-MM" → first day of month
        [$year, $month] = explode('-', $request->start_date);
        $startDate = "{$year}-{$month}-01";

        // Calculate end date
        $endDate = date('Y-m-t', strtotime("+{$request->duration_years} years -1 month", strtotime($startDate)));

        // Operation start date
        $opStart = null;
        if ($request->operation_start_date) {
            [$oy, $om] = explode('-', $request->operation_start_date);
            $opStart = "{$oy}-{$om}-01";
        }

        $id = DB::table('financial_studies')->insertGetId([
            'portfolio_company_id'           => $companyId,
            'name'                           => $request->name,
            'study_currency'                 => $request->study_currency,
            'study_start_date'               => $startDate,
            'study_end_date'                 => $endDate,
            'duration_years'                 => $request->duration_years,
            'operation_start_date'           => $opStart,
            'business_type'                  => $businessType,
            'business_sector'                => $request->business_sector ?? null,
            'corporate_tax_rate'             => $request->corporate_tax_rate,
            'required_investment_return_pct' => $request->required_investment_return_pct ?? 0,
            'perpetual_growth_rate_pct'      => $request->perpetual_growth_rate_pct ?? 0,
            'general_assumptions'            => json_encode([
                'new_company'      => (bool) $request->new_company,
                'intro_paragraph'  => $request->intro_paragraph ?? '',
                'raw_materials'    => $request->raw_materials ?? [],
            ]),
            'products'    => json_encode($request->products ?? []),
            'projections' => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        if ($request->submit_button === 'next') {
            return redirect()->route('financial-studies.sales', [$companyId, $id])
                ->with('success', 'Study created! Now set up the sales projection.');
        }

        return redirect()->route('financial-studies.index', $companyId)
            ->with('success', 'Study "' . $request->name . '" saved successfully.');
    }

    // ─────────────────────────────────────────────
    //  EDIT  — show form with existing data
    // ─────────────────────────────────────────────
    public function edit($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $general    = json_decode($study->general_assumptions ?? '{}', true) ?? [];
        $products   = json_decode($study->products ?? '[]', true) ?? [];

        // Format start_date back to YYYY-MM for the month input
        $startDate = $study->study_start_date
            ? substr($study->study_start_date, 0, 7)
            : '';

        $opStart = $study->operation_start_date
            ? substr($study->operation_start_date, 0, 7)
            : '';

        return Inertia::render('FinancialStudies/Create', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'study'   => [
                'id'                             => $study->id,
                'name'                           => $study->name,
                'study_currency'                 => $study->study_currency,
                'duration_years'                 => $study->duration_years,
                'business_type'                  => $study->business_type,
                'business_sector'                => $study->business_sector,
                'corporate_tax_rate'             => $study->corporate_tax_rate,
                'required_investment_return_pct' => $study->required_investment_return_pct,
                'perpetual_growth_rate_pct'      => $study->perpetual_growth_rate_pct,
                'new_company'                    => $general['new_company'] ?? true,
                'intro_paragraph'                => $general['intro_paragraph'] ?? '',
                'raw_materials'                  => $general['raw_materials'] ?? [],
            ],
            'startDate'    => $startDate,
            'opStartDate'  => $opStart,
            'products'     => $products,
            'currencies'   => $this->currencies(),
            'measurementUnits'      => $this->measurementUnits(),
            'inventoryCoverageDays' => $this->inventoryCoverageDays(),
        ]);
    }

    // ─────────────────────────────────────────────
    //  UPDATE
    // ─────────────────────────────────────────────
    public function update(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $request->validate([
            'name'               => 'required|string|max:255',
            'study_currency'     => 'required|string',
            'start_date'         => 'required|string',
            'duration_years'     => 'required|integer|min:1|max:20',
            'new_company'        => 'nullable|boolean',
            'corporate_tax_rate' => 'required|numeric|min:0|max:100',
            'required_investment_return_pct' => $request->duration_years > 2 ? 'required|numeric|min:0|max:100' : 'nullable|numeric|min:0|max:100',
            'perpetual_growth_rate_pct'      => $request->duration_years > 2 ? 'required|numeric|min:0|max:20'  : 'nullable|numeric|min:0|max:20',
            'products'           => 'required|array|min:1',
            'products.*.name'    => 'required|string|max:255',
            'products.*.nature'  => 'required|in:manufacturing,trading,service',
        ]);

        // Auto-derive business_type from product natures
        $natures = collect($request->products)->pluck('nature')->unique()->values()->toArray();
        $businessType = count($natures) === 1 ? $natures[0] : 'mixed';

        [$year, $month] = explode('-', $request->start_date);
        $startDate = "{$year}-{$month}-01";
        $endDate   = date('Y-m-t', strtotime("+{$request->duration_years} years -1 month", strtotime($startDate)));

        $opStart = null;
        if ($request->operation_start_date) {
            [$oy, $om] = explode('-', $request->operation_start_date);
            $opStart = "{$oy}-{$om}-01";
        }

        DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->update([
                'name'                           => $request->name,
                'study_currency'                 => $request->study_currency,
                'study_start_date'               => $startDate,
                'study_end_date'                 => $endDate,
                'duration_years'                 => $request->duration_years,
                'operation_start_date'           => $opStart,
                'business_type'                  => $businessType,
                'business_sector'                => $request->business_sector ?? null,
                'corporate_tax_rate'             => $request->corporate_tax_rate,
                'required_investment_return_pct' => $request->required_investment_return_pct ?? 0,
                'perpetual_growth_rate_pct'      => $request->perpetual_growth_rate_pct ?? 0,
                'general_assumptions'            => json_encode([
                    'new_company'      => (bool) $request->new_company,
                    'intro_paragraph'  => $request->intro_paragraph ?? '',
                    'raw_materials'    => $request->raw_materials ?? [],
                ]),
                'products'   => json_encode($request->products ?? []),
                'updated_at' => now(),
            ]);

        if ($request->submit_button === 'next') {
            return redirect()->route('financial-studies.sales', [$companyId, $studyId])
                ->with('success', 'Study updated! Now set up the sales projection.');
        }

        return redirect()->route('financial-studies.index', $companyId)
            ->with('success', 'Study updated successfully.');
    }

    // ─────────────────────────────────────────────
    //  DESTROY
    // ─────────────────────────────────────────────
    public function destroy($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->delete();

        return redirect()->route('financial-studies.index', $companyId)
            ->with('success', 'Study deleted.');
    }

    // ─────────────────────────────────────────────
    //  SALES STEP — show Sales Projection page
    // ─────────────────────────────────────────────
    public function salesStep($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $products    = json_decode($study->products ?? '[]', true) ?? [];
        $projections = json_decode($study->projections ?? 'null', true);
        $writeups    = json_decode($study->writeups ?? '{}', true) ?? [];

        return Inertia::render('FinancialStudies/SalesProjection', [
            'company'  => ['id' => $company->id, 'name' => $company->name],
            'study'    => [
                'id'               => $study->id,
                'name'             => $study->name,
                'duration_years'   => (int) $study->duration_years,
                'study_currency'   => $study->study_currency,
                'study_start_date' => $study->study_start_date,
                'study_end_date'   => $study->study_end_date,
            ],
            'products'      => $products,
            'existingSales' => $projections['sales'] ?? null,
            'writeupText'   => $writeups['sales']['text'] ?? '',
            'currentStep'   => 2,
        ]);
    }

    // ─────────────────────────────────────────────
    //  SAVE SALES STEP
    // ─────────────────────────────────────────────
    public function saveSalesStep(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $study = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        // Merge into existing projections (preserve other steps)
        $existing    = $study->projections ?? '{}';
        $projections = json_decode($existing, true);
        if (!is_array($projections)) $projections = [];

        $projections['sales'] = $request->input('sales_data', []);

        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'projections' => json_encode($projections),
                'updated_at'  => now(),
            ]);

        $action = $request->input('submit_button', 'save');

        if ($action === 'next') {
            return response()->json([
                'success'  => true,
                'redirect' => route('financial-studies.cogs', [$companyId, $studyId]),
            ]);
        }

        return response()->json([
            'success'  => true,
            'redirect' => route('financial-studies.index', $companyId),
        ]);
    }

    // ─────────────────────────────────────────────
    //  COGS STEP — show COGS page
    // ─────────────────────────────────────────────
    public function cogsStep($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $products = json_decode($study->products ?? '[]', true) ?? [];
        $cogsData = json_decode($study->cogs_data ?? '[]', true) ?? [];
        $writeups     = json_decode($study->writeups ?? '{}', true) ?? [];

        // Pull raw_materials from general_assumptions (set in Step 1)
        $general      = json_decode($study->general_assumptions ?? '{}', true) ?? [];
        $rawMaterials = $general['raw_materials'] ?? [];

        return Inertia::render('FinancialStudies/CogsStep', [
            'company'      => ['id' => $company->id, 'name' => $company->name],
            'study'        => [
                'id'               => $study->id,
                'name'             => $study->name,
                'duration_years'   => (int) $study->duration_years,
                'study_currency'   => $study->study_currency,
                'start_date'       => $study->study_start_date
                    ? substr($study->study_start_date, 0, 7)
                    : '',
                'raw_materials'    => $rawMaterials,
            ],
            'products' => $products,
            'cogsData' => $cogsData,
            'writeupText' => $writeups['cogs']['text'] ?? '',
        ]);
    }

    // ─────────────────────────────────────────────
    //  SAVE COGS STEP
    // ─────────────────────────────────────────────
    public function saveCogsStep(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $request->validate([
            'cogs_data'     => 'nullable|array',
            'submit_button' => 'required|string|in:save,next',
        ]);

        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'cogs_data'  => json_encode($request->input('cogs_data')),
                'updated_at' => now(),
            ]);

        if ($request->input('submit_button') === 'next') {
            return response()->json([
                'success'  => true,
                'redirect' => route('financial-studies.manpower', [$companyId, $studyId]),
            ]);
        }

        return response()->json([
            'success'  => true,
            'redirect' => null,
        ]);
    }

    // ─────────────────────────────────────────────
    //  MANPOWER STEP — show Manpower page
    // ─────────────────────────────────────────────
    public function manpowerStep($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $manpowerData = json_decode($study->manpower_data ?? '[]', true) ?? [];
        $writeups     = json_decode($study->writeups ?? '{}', true) ?? [];
        $products     = json_decode($study->products ?? '[]', true) ?? [];

        return Inertia::render('FinancialStudies/ManpowerStep', [
            'company'      => ['id' => $company->id, 'name' => $company->name],
            'study'        => [
                'id'             => $study->id,
                'name'           => $study->name,
                'duration_years' => (int) $study->duration_years,
                'study_currency' => $study->study_currency,
                'start_date'     => $study->study_start_date
                    ? substr($study->study_start_date, 0, 7)
                    : '',
            ],
            'products'     => $products,
            'manpowerData' => $manpowerData,
            'writeupText'  => $writeups['manpower']['text'] ?? '',
        ]);
    }

    // ─────────────────────────────────────────────
    //  SAVE MANPOWER STEP
    //  FIX: changed 'required|array' → 'nullable|array'
    //  so saving an empty department list does not fail validation
    // ─────────────────────────────────────────────
    public function saveManpowerStep(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $request->validate([
            'manpower_data' => 'nullable|array',
            'submit_button' => 'required|string|in:save,next',
        ]);

        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'manpower_data' => json_encode($request->input('manpower_data', [])),
                'updated_at'    => now(),
            ]);

        if ($request->input('submit_button') === 'next') {
            return response()->json([
                'success'  => true,
                'redirect' => route('financial-studies.expenses', [$companyId, $studyId]),
            ]);
        }

        return response()->json([
            'success'  => true,
            'redirect' => null,
        ]);
    }

    // ─────────────────────────────────────────────
    //  EXPENSES STEP — show Expenses page
    // ─────────────────────────────────────────────
    public function expensesStep($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $expensesData = json_decode($study->expenses_data ?? '[]', true) ?? [];
        $products     = json_decode($study->products ?? '[]', true) ?? [];
        $writeups     = json_decode($study->writeups ?? '{}', true) ?? [];

        return Inertia::render('FinancialStudies/ExpensesPlanStep', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'study'   => [
                'id'             => $study->id,
                'name'           => $study->name,
                'duration_years' => (int) $study->duration_years,
                'study_currency' => $study->study_currency,
                'start_date'     => $study->study_start_date
                    ? substr($study->study_start_date, 0, 7)
                    : '',
            ],
            'products'     => $products,
            'expensesData' => $expensesData,
            'writeupText'  => $writeups['expenses']['text'] ?? '',
        ]);
    }

    // ─────────────────────────────────────────────
    //  SAVE EXPENSES STEP
    // ─────────────────────────────────────────────
    public function saveExpensesStep(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $request->validate([
            'expenses_data' => 'nullable|array',
            'submit_button' => 'required|string|in:save,next',
        ]);

        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'expenses_data' => json_encode($request->input('expenses_data', [])),
                'updated_at'    => now(),
            ]);

        if ($request->input('submit_button') === 'next') {
            return response()->json([
                'success'  => true,
                'redirect' => route('financial-studies.fixed-assets', [$companyId, $studyId]), // TODO: fixed-assets step
            ]);
        }

        return response()->json([
            'success'  => true,
            'redirect' => null,
        ]);
    }



// ═══════════════════════════════════════════════════════════════════════════
// FIX 2 — Add these TWO methods to FinancialStudyController.php
// Place them AFTER saveExpensesStep() and BEFORE saveWriteup()
// ═══════════════════════════════════════════════════════════════════════════

    // ─────────────────────────────────────────────
    //  FIXED ASSETS STEP — show page
    // ─────────────────────────────────────────────
    public function fixedAssetsStep($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $fixedAssetsData = json_decode($study->fixed_assets_data ?? '[]', true) ?? [];
        $products        = json_decode($study->products ?? '[]', true) ?? [];
        $writeups        = json_decode($study->writeups ?? '{}', true) ?? [];

        return Inertia::render('FinancialStudies/FixedAssetsStep', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'study'   => [
                'id'             => $study->id,
                'name'           => $study->name,
                'duration_years' => (int) $study->duration_years,
                'study_currency' => $study->study_currency,
                'start_date'     => $study->study_start_date
                    ? substr($study->study_start_date, 0, 7)
                    : '',
            ],
            'products'        => $products,
            'fixedAssetsData' => $fixedAssetsData,
            'writeupText'     => $writeups['fixed_assets']['text'] ?? '',
        ]);
    }

    // ─────────────────────────────────────────────
    //  SAVE FIXED ASSETS STEP
    // ─────────────────────────────────────────────
    public function saveFixedAssetsStep(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $request->validate([
            'fixed_assets_data' => 'nullable|array',
            'submit_button'     => 'required|string|in:save,next',
        ]);

        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'fixed_assets_data' => json_encode($request->input('fixed_assets_data', [])),
                'updated_at'        => now(),
            ]);

        if ($request->input('submit_button') === 'next') {
            return response()->json([
                'success'  => true,
                'redirect' => route('financial-studies.opening-balance', [$companyId, $studyId]),
            ]);
        }

        return response()->json([
            'success'  => true,
            'redirect' => null,
        ]);
    }



// ═══════════════════════════════════════════════════════════════════
// ADD THESE TWO METHODS TO FinancialStudyController.php
// ═══════════════════════════════════════════════════════════════════

  
// ═══════════════════════════════════════════════════════════════════
// REPLACE the two opening balance methods in FinancialStudyController.php
// with these fixed versions
// ═══════════════════════════════════════════════════════════════════

    // ── Step 7: Opening Balance ───────────────────────────────────────
    public function openingBalanceStep($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = \DB::table('portfolio_companies')->where('id', $companyId)->first();
        $study   = \DB::table('financial_studies')->where('id', $studyId)->first();

        if (!$company || !$study) abort(404);

        // ── FIX 1: new_company lives inside financial_studies.general_assumptions JSON
        //    NOT in portfolio_companies.company_phase column
        $generalAssumptions = json_decode($study->general_assumptions ?? '{}', true) ?? [];
        $isNewCompany = $generalAssumptions['new_company'] ?? true;
        $phase = $isNewCompany ? 'new' : 'existing';

        // ── FIX 2: use the study's own currency, not the company's base_currency
        $studyCurrency = $study->study_currency ?? 'USD';

        // Saved opening balance data (if user already filled this step)
        $savedData = $study->opening_balance
            ? json_decode($study->opening_balance, true)
            : null;

        // For existing companies — try to load the latest balance sheet
        $latestStatement  = null;
        $balanceSheetData = null;

        if ($phase === 'existing') {
            // Get the most recent financial statement for this company
            $latestStatement = \DB::table('financial_statements')
                ->where('portfolio_company_id', $companyId)
                ->orderBy('period_to', 'desc')
                ->first();

            if ($latestStatement) {
                // Load all balance sheet sections + line items
                $sections = \DB::table('fs_sections')
                    ->where('financial_statement_id', $latestStatement->id)
                    ->where('statement_type', 'balance_sheet')
                    ->orderBy('sort_order')
                    ->get();

                $bsData = [];
                foreach ($sections as $section) {
                    $items = \DB::table('fs_line_items')
                        ->where('fs_section_id', $section->id)
                        ->orderBy('sort_order')
                        ->get()
                        ->map(function ($i) {
                            // Fetch settlement schedule for this line item (if any)
                            $schedule = \DB::table('fs_settlement_schedules')
                                ->where('fs_line_item_id', $i->id)
                                ->orderBy('month')
                                ->get()
                                ->map(fn($s) => [
                                    'month'  => $s->month,
                                    'amount' => (float) $s->amount,
                                    'notes'  => $s->notes ?? null,
                                ])
                                ->toArray();

                            return [
                                'id'       => $i->id,
                                'label'    => $i->label,
                                'amount'   => (float) $i->amount,
                                'schedule' => $schedule,          // monthly settlement rows
                                'scheduled_total' => array_sum(array_column($schedule, 'amount')),
                            ];
                        })
                        ->toArray();

                    $bsData[] = [
                        'section_key' => $section->section_key,
                        'label'       => $section->display_name,
                        'computed'    => (bool) $section->is_computed,
                        'items'       => $items,
                    ];
                }
                $balanceSheetData = $bsData;
            }
        }

        return \Inertia\Inertia::render('FinancialStudies/OpeningBalanceStep', [
            'company'          => [
                'id'            => $company->id,
                'name'          => $company->name,
                'currency'      => $studyCurrency,   // ← FIX 2: study currency
                'company_phase' => $phase,            // ← FIX 1: from general_assumptions
            ],
            'study'            => [
                'id'   => $study->id,
                'name' => $study->name,
            ],
            'latestStatement'  => $latestStatement ? [
                'id'          => $latestStatement->id,
                'period_from' => $latestStatement->period_from,
                'period_to'   => $latestStatement->period_to,
                'notes'       => $latestStatement->notes,
            ] : null,
            'balanceSheetData' => $balanceSheetData,
            'savedData'        => $savedData,
        ]);
    }

    public function saveOpeningBalanceStep(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $study = \DB::table('financial_studies')->where('id', $studyId)->first();
        if (!$study) abort(404);

        $validated = $request->validate([
            'sections'   => 'nullable|array',
            'source'     => 'nullable|string|in:auto,manual,none',
            'as_of_date' => 'nullable|date',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $data = [
            'source'     => $validated['source']     ?? 'manual',
            'as_of_date' => $validated['as_of_date'] ?? null,
            'notes'      => $validated['notes']       ?? null,
            'sections'   => $validated['sections']   ?? [],
        ];

        \DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'opening_balance' => json_encode($data),
                'updated_at'      => now(),
            ]);

        return response()->json([
            'success'  => true,
            'redirect' => route('financial-studies.results', [$companyId, $studyId]),
        ]);
    }
 
   

    // ═══════════════════════════════════════════════════════════════════════════
//  FILE 1: Add this method to FinancialStudyController.php
//  Place it after openingBalanceStep() / saveOpeningBalanceStep()
// ═══════════════════════════════════════════════════════════════════════════

    // ── Step 8: Results ───────────────────────────────────────────────────────
    public function resultsStep($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        // Decode all study data blobs
        $products        = json_decode($study->products          ?? '[]',   true) ?? [];
        $projectionsRaw  = json_decode($study->projections       ?? '{}',   true) ?? [];
        $cogsData        = json_decode($study->cogs_data         ?? '[]',   true) ?? [];
        $manpowerData    = json_decode($study->manpower_data     ?? '[]',   true) ?? [];
        $expensesData    = json_decode($study->expenses_data     ?? '[]',   true) ?? [];
        $fixedAssetsData = json_decode($study->fixed_assets_data ?? '[]',   true) ?? [];
        $openingBalance  = json_decode($study->opening_balance   ?? 'null', true);

        // Raw materials live inside general_assumptions (set in Create.vue Step 1)
        $generalAssumptions = json_decode($study->general_assumptions ?? '{}', true) ?? [];
        $rawMaterials       = $generalAssumptions['raw_materials'] ?? [];

        // SalesProjection.vue sends: { products: [...] }
        // saveSalesStep stores it as: projections['sales'] = { products: [...] }
        // So the actual product projections are at projections.sales.products
        $salesData = $projectionsRaw['sales'] ?? [];
        if (isset($salesData['products'])) {
            $projections = $salesData;  // { products: [...] }
        } else {
            $projections = ['products' => []];
        }

        return Inertia::render('FinancialStudies/ResultsStep', [
            'company' => [
                'id'   => $company->id,
                'name' => $company->name,
            ],
            'study' => [
                'id'                             => $study->id,
                'name'                           => $study->name,
                'study_currency'                 => $study->study_currency,
                'duration_years'                 => (int) $study->duration_years,
                'study_start_date'               => $study->study_start_date,
                'study_end_date'                 => $study->study_end_date,
                'corporate_tax_rate'             => (float) ($study->corporate_tax_rate             ?? 0),
                'required_investment_return_pct' => (float) ($study->required_investment_return_pct ?? 10),
                'perpetual_growth_rate_pct'      => (float) ($study->perpetual_growth_rate_pct      ?? 3),
                'business_type'                  => $study->business_type,
            ],
            'products'        => $products,
            'projections'     => $projections,
            'cogsData'        => $cogsData,
            'manpowerData'    => $manpowerData,
            'expensesData'    => $expensesData,
            'fixedAssetsData' => $fixedAssetsData,
            'openingBalance'  => $openingBalance,
            'rawMaterials'    => $rawMaterials,
        ]);
    }




// ═══════════════════════════════════════════════════════════════════════════
//  FILE 3: Update the redirect in saveOpeningBalanceStep()
//  Find this line:
//      'redirect' => route('financial-studies.report', [$companyId, $studyId]),
//  Change it to:
//      'redirect' => route('financial-studies.results', [$companyId, $studyId]),
// ═══════════════════════════════════════════════════════════════════════════


// ═══════════════════════════════════════════════════════════════════════════
//  FILE 4: Update SalesProjection.vue wizardSteps array
//  The 8th step label should match:
//
//  const wizardSteps = [
//    'Setup & Products',
//    'Sales Projection',
//    'COGS',
//    'Manpower',
//    'Expenses',
//    'Fixed Assets',
//    'Opening Balance',
//    'Results',          ← Step 8
//  ]
//
//  Also update the "Save & Next" redirect in saveSalesStep() in the controller
//  to ensure it correctly chains:
//  Setup → Sales → COGS → Manpower → Expenses → Fixed Assets → Opening Balance → Results
// ═══════════════════════════════════════════════════════════════════════════


// ═══════════════════════════════════════════════════════════════════════════
//  HOW THE PROJECTIONS DATA FLOWS
//  ─────────────────────────────
//  saveSalesStep() stores: financial_studies.projections = JSON of:
//    { products: [ { year1_months: [...], year2_months: [...], annual_years: [...], ... } ] }
//
//  OR it may store an array directly (old format).
//  The resultsStep() controller handles BOTH formats above.
//
//  The Vue engine (StudyResultsEngine.js) expects:
//    projections = { products: [ {...}, {...} ] }
//  So the controller normalises it before passing to Inertia.
// ═══════════════════════════════════════════════════════════════════════════
   

   





    // ─────────────────────────────────────────────
    //  SAVE WRITE-UP — auto-save per step
    // ─────────────────────────────────────────────
    public function saveWriteup(Request $request, $companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $study = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $request->validate([
            'step' => 'required|string|in:setup,sales,cogs,manpower,expenses,fixed_assets,opening_balance,results',
            'text' => 'nullable|string',
            'lang' => 'nullable|string|in:en,ar',
        ]);

        $writeups = json_decode($study->writeups ?? '{}', true) ?? [];
        $writeups[$request->step] = [
            'text'       => $request->text ?? '',
            'lang'       => $request->lang ?? 'en',
            'updated_at' => now()->toDateTimeString(),
        ];

        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'writeups'   => json_encode($writeups),
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────
    //  REPORT PAGE — assemble all write-ups
    // ─────────────────────────────────────────────
    public function reportPage($companyId, $studyId)
    {
        $this->authorizeStudy((int) $companyId);
        $company = DB::table('portfolio_companies')->where('id', $companyId)->firstOrFail();
        $study   = DB::table('financial_studies')
            ->where('id', $studyId)
            ->where('portfolio_company_id', $companyId)
            ->firstOrFail();

        $writeups        = json_decode($study->writeups          ?? '{}',   true) ?? [];
        $products        = json_decode($study->products          ?? '[]',   true) ?? [];
        $manpowerData    = json_decode($study->manpower_data     ?? '[]',   true) ?? [];
        $expensesData    = json_decode($study->expenses_data     ?? '[]',   true) ?? [];
        $cogsData        = json_decode($study->cogs_data         ?? '[]',   true) ?? [];
        $fixedAssetsData = json_decode($study->fixed_assets_data ?? '[]',   true) ?? [];
        $openingBalance  = json_decode($study->opening_balance   ?? 'null', true);

        $projectionsRaw = json_decode($study->projections ?? '{}', true) ?? [];
        $salesData      = $projectionsRaw['sales'] ?? [];
        $projections    = isset($salesData['products']) ? $salesData : ['products' => []];

        return Inertia::render('FinancialStudies/StudyReport', [
            'company'  => ['id' => $company->id, 'name' => $company->name],
            'study'    => [
                'id'               => $study->id,
                'name'             => $study->name,
                'study_currency'   => $study->study_currency,
                'duration_years'   => (int) $study->duration_years,
                'study_start_date' => $study->study_start_date,
                'study_end_date'   => $study->study_end_date,
                'business_type'    => $study->business_type,
                'business_sector'  => $study->business_sector,
            ],
            'writeups'        => $writeups,
            'products'        => $products,
            'manpowerData'    => $manpowerData,
            'expensesData'    => $expensesData,
            'cogsData'        => $cogsData,
            'fixedAssetsData' => $fixedAssetsData,
            'openingBalance'  => $openingBalance,
            'projections'     => $projections,
        ]);
    }

    // ─────────────────────────────────────────────
    //  API — import expense names from expense_data
    // ─────────────────────────────────────────────
    public function importExpenseNames($companyId)
    {
        $this->authorizeStudy((int) $companyId);
        $names = DB::table('expense_data')
            ->join('expense_uploads', 'expense_uploads.id', '=', 'expense_data.upload_id')
            ->where('expense_uploads.portfolio_company_id', $companyId)
            ->whereNotNull('expense_data.expense_name')
            ->where('expense_data.expense_name', '!=', '')
            ->select('expense_data.expense_name')
            ->distinct()
            ->orderBy('expense_data.expense_name')
            ->pluck('expense_name')
            ->values();

        return response()->json(['names' => $names]);
    }

    // ─────────────────────────────────────────────
    //  API — import products/categories from sales_data
    // ─────────────────────────────────────────────
    public function importFromSales($companyId)
    {
        $this->authorizeStudy((int) $companyId);
        $cutoff = now()->subMonths(12)->startOfMonth()->format('Y-m-d');

        // product_item — individual products
        $products = DB::table('sales_data')
            ->where('portfolio_company_id', $companyId)
            ->where('date', '>=', $cutoff)
            ->whereNotNull('product_item')
            ->where('product_item', '!=', '')
            ->distinct()
            ->orderBy('product_item')
            ->pluck('product_item')
            ->values();

        // product_category — grouped categories
        $categories = DB::table('sales_data')
            ->where('portfolio_company_id', $companyId)
            ->where('date', '>=', $cutoff)
            ->whereNotNull('product_category')
            ->where('product_category', '!=', '')
            ->distinct()
            ->orderBy('product_category')
            ->pluck('product_category')
            ->values();

        return response()->json([
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    // ─────────────────────────────────────────────
    //  API — import dimension values from sales_data
    // ─────────────────────────────────────────────
    public function importSalesDimension($companyId)
    {
        $this->authorizeStudy((int) $companyId);
        $column = request()->query('column', '');

        $columnMap = [
            'product'  => 'product_item',
            'category' => 'product_category',
        ];
        $dbColumn = $columnMap[$column] ?? $column;

        $allowed = ['sales_channel', 'business_sector', 'customer_name', 'product_item', 'product_category'];
        if (!in_array($dbColumn, $allowed)) {
            return response()->json(['items' => []]);
        }

        $cutoff = now()->subMonths(12)->startOfMonth()->format('Y-m-d');

        $items = DB::table('sales_data')
            ->join('sales_uploads', 'sales_uploads.id', '=', 'sales_data.upload_id')
            ->where('sales_uploads.portfolio_company_id', $companyId)
            ->where('sales_data.date', '>=', $cutoff)
            ->whereNotNull("sales_data.{$dbColumn}")
            ->where("sales_data.{$dbColumn}", '!=', '')
            ->select("sales_data.{$dbColumn}")
            ->distinct()
            ->orderBy("sales_data.{$dbColumn}")
            ->pluck($dbColumn)
            ->values();

        return response()->json(['items' => $items]);
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────
    private function currencies(): array
    {
        return [
            'EGP' => 'EGP — Egyptian Pound',
            'USD' => 'USD — US Dollar',
            'EUR' => 'EUR — Euro',
            'GBP' => 'GBP — British Pound',
            'SAR' => 'Saudi Riyal',
            'AED' => 'UAE Dirham',
        ];
    }

    private function measurementUnits(): array
    {
        return [
            'unit'  => 'Unit',
            'kg'    => 'Kilogram (kg)',
            'ton'   => 'Ton',
            'liter' => 'Liter',
            'meter' => 'Meter',
            'sqm'   => 'Sq. Meter (m²)',
            'hour'  => 'Hour',
            'box'   => 'Box',
            'pcs'   => 'Pieces',
        ];
    }

    private function inventoryCoverageDays(): array
    {
        return [
            '0'   => '0 days',
            '7'   => '7 days',
            '15'  => '15 days',
            '30'  => '30 days (1 month)',
            '45'  => '45 days',
            '60'  => '60 days (2 months)',
            '90'  => '90 days (3 months)',
            '120' => '120 days (4 months)',
            '180' => '180 days (6 months)',
        ];
    }
}