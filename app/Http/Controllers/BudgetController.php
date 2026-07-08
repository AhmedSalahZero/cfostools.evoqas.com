<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\PortfolioCompany;
use App\Models\BudgetStatement;
use App\Models\BudgetSection;
use App\Models\BudgetGroup;
use App\Models\BudgetLineItem;
use App\Models\BudgetActual;
use App\Models\FinancialStatement;
use App\Models\FsSection;

class BudgetController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // FIXED SECTION TEMPLATES  (Grandpa layer — never changes)
    // ─────────────────────────────────────────────────────────────────────

    private function incomeSections(): array
    {
        return [
            ['key' => 'sales_revenue',         'label' => 'Sales Revenues',                     'computed' => false, 'from' => null,                                                                                                                                    'sort' => 1],
            ['key' => 'cogs',                   'label' => 'Cost of Goods Sold / Cost of Service','computed' => false, 'from' => null,                                                                                                                                    'sort' => 2],
            ['key' => 'gross_profit',           'label' => 'Gross Profit',                       'computed' => true,  'from' => [['key'=>'sales_revenue','sign'=>1],['key'=>'cogs','sign'=>-1]],                                                                           'sort' => 3],
            ['key' => 'marketing_expenses',     'label' => 'Marketing & Sales Expenses',         'computed' => false, 'from' => null,                                                                                                                                    'sort' => 4],
            ['key' => 'ga_expenses',            'label' => 'General & Administrative Expenses',  'computed' => false, 'from' => null,                                                                                                                                    'sort' => 5],
            ['key' => 'ebitda',                 'label' => 'EBITDA',                             'computed' => true,  'from' => [['key'=>'gross_profit','sign'=>1],['key'=>'marketing_expenses','sign'=>-1],['key'=>'ga_expenses','sign'=>-1]],                            'sort' => 6],
            ['key' => 'depreciation',           'label' => 'Depreciation & Amortization',       'computed' => false, 'from' => null,                                                                                                                                    'sort' => 7],
            ['key' => 'ebit',                   'label' => 'EBIT',                               'computed' => true,  'from' => [['key'=>'ebitda','sign'=>1],['key'=>'depreciation','sign'=>-1]],                                                                         'sort' => 8],
            ['key' => 'finance_income_expense', 'label' => 'Finance Income & (Expenses)',        'computed' => false, 'from' => null,                                                                                                                                    'sort' => 9],
            ['key' => 'ebt',                    'label' => 'Earnings Before Tax (EBT)',          'computed' => true,  'from' => [['key'=>'ebit','sign'=>1],['key'=>'finance_income_expense','sign'=>1]],                                                                  'sort' => 10],
            ['key' => 'taxes',                  'label' => 'Income Taxes',                      'computed' => false, 'from' => null,                                                                                                                                    'sort' => 11],
            ['key' => 'net_profit',             'label' => 'Net Profit',                        'computed' => true,  'from' => [['key'=>'ebt','sign'=>1],['key'=>'taxes','sign'=>-1]],                                                                                   'sort' => 12],
        ];
    }

    private function balanceSections(): array
    {
        return [
            ['key' => 'current_assets',         'label' => 'Current Assets',               'computed' => false, 'from' => null,                                                                                     'sort' => 1],
            ['key' => 'non_current_assets',      'label' => 'Non-Current Assets',          'computed' => false, 'from' => null,                                                                                     'sort' => 2],
            ['key' => 'total_assets',            'label' => 'Total Assets',                'computed' => true,  'from' => [['key'=>'current_assets','sign'=>1],['key'=>'non_current_assets','sign'=>1]],             'sort' => 3],
            ['key' => 'current_liabilities',     'label' => 'Current Liabilities',         'computed' => false, 'from' => null,                                                                                     'sort' => 4],
            ['key' => 'non_current_liabilities', 'label' => 'Non-Current Liabilities',     'computed' => false, 'from' => null,                                                                                     'sort' => 5],
            ['key' => 'total_liabilities',       'label' => 'Total Liabilities',           'computed' => true,  'from' => [['key'=>'current_liabilities','sign'=>1],['key'=>'non_current_liabilities','sign'=>1]],  'sort' => 6],
            ['key' => 'equity',                  'label' => "Shareholders' Equity",        'computed' => false, 'from' => null,                                                                                     'sort' => 7],
            ['key' => 'total_equity',            'label' => 'Total Equity',                'computed' => true,  'from' => [['key'=>'equity','sign'=>1]],                                                            'sort' => 8],
            ['key' => 'total_liabilities_equity','label' => 'Total Liabilities & Equity',  'computed' => true,  'from' => [['key'=>'total_liabilities','sign'=>1],['key'=>'total_equity','sign'=>1]],               'sort' => 9],
        ];
    }

    private function cashflowSections(): array
    {
        return [
            ['key' => 'cfo',      'label' => 'Cash from Operating Activities', 'computed' => false, 'from' => null,                                                                          'sort' => 1],
            ['key' => 'cfi',      'label' => 'Cash from Investing Activities', 'computed' => false, 'from' => null,                                                                          'sort' => 2],
            ['key' => 'cff',      'label' => 'Cash from Financing Activities', 'computed' => false, 'from' => null,                                                                          'sort' => 3],
            ['key' => 'net_cash', 'label' => 'Net Change in Cash',             'computed' => true,  'from' => [['key'=>'cfo','sign'=>1],['key'=>'cfi','sign'=>1],['key'=>'cff','sign'=>1]],   'sort' => 4],
        ];
    }

    private function allSectionTemplates(): array
    {
        return array_merge(
            array_map(fn($s) => array_merge($s, ['type' => 'income']),       $this->incomeSections()),
            array_map(fn($s) => array_merge($s, ['type' => 'balance_sheet']), $this->balanceSections()),
            array_map(fn($s) => array_merge($s, ['type' => 'cashflow']),      $this->cashflowSections()),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPER: guard company access
    // ─────────────────────────────────────────────────────────────────────
    private function getCompany($companyId): PortfolioCompany
    {
        return $this->authorizeCompany((int) $companyId, 'budget_variance');
    }

    // ─────────────────────────────────────────────────────────────────────
    // INDEX  GET /portfolio-companies/{company}/budgets
    // ─────────────────────────────────────────────────────────────────────
    public function index(PortfolioCompany $company)
    {
        $this->getCompany($company->id);

        $budgets = BudgetStatement::where('portfolio_company_id', $company->id)
            ->orderByDesc('year')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => [
                'id'         => $b->id,
                'name'       => $b->name,
                'year'       => $b->year,
                'currency'   => $b->currency,
                'status'     => $b->status,
                'created_at' => $b->created_at->format('d M Y'),
            ]);

        return Inertia::render('Budgets/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'budgets' => $budgets,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CREATE  GET /portfolio-companies/{company}/budgets/create
    // ─────────────────────────────────────────────────────────────────────
    public function create(PortfolioCompany $company)
    {
        $this->getCompany($company->id);

        // Load all users in this org for director assignment
        $orgUsers = \App\Models\User::where('organization_id', $company->organization_id)
            ->orderBy('name')
            ->get()
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return Inertia::render('Budgets/Create', [
            'company'          => [
                'id'       => $company->id,
                'name'     => $company->name,
                'currency' => $company->invested_currency ?? 'USD',
            ],
            'incomeSections'   => $this->incomeSections(),
            'balanceSections'  => $this->balanceSections(),
            'cashflowSections' => $this->cashflowSections(),
            'currentYear'      => (int) now()->format('Y'),
            'orgUsers'         => $orgUsers,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // STORE  POST /portfolio-companies/{company}/budgets
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request, PortfolioCompany $company)
    {
        $this->getCompany($company->id);

        $request->validate([
            'name'               => 'required|string|max:255',
            'year'               => 'required|integer|min:2000|max:2100',
            'status'             => 'required|in:draft,final',
            'notes'              => 'nullable|string|max:2000',
            'sections'           => 'required|array',
            'sales_directors'    => 'nullable|array',
            'line_item_assignments' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $company) {
            $budget = BudgetStatement::create([
                'portfolio_company_id' => $company->id,
                'name'                 => $request->name,
                'year'                 => $request->year,
                'currency'             => $company->invested_currency ?? 'USD',
                'status'               => $request->status,
                'notes'                => $request->notes,
                'created_by'           => auth()->id(),
            ]);

            $lineItemKeyMap = $this->saveSections($budget, $request->sections);
            $this->saveSalesDirectors($budget, $request->sales_directors ?? [], $request->line_item_assignments ?? [], $lineItemKeyMap);
        });

        return redirect()
            ->route('budgets.index', $company->id)
            ->with('success', 'Budget statement created successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // EDIT  GET /portfolio-companies/{company}/budgets/{budget}/edit
    // ─────────────────────────────────────────────────────────────────────
    public function edit(PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $sections = BudgetSection::where('budget_statement_id', $budget->id)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($sec) {
                $groups = BudgetGroup::where('budget_section_id', $sec->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(function ($grp) {
                        $items = BudgetLineItem::where('budget_group_id', $grp->id)
                            ->orderBy('sort_order')
                            ->get()
                            ->map(fn($li) => [
                                'id'              => $li->id,
                                'label'           => $li->label,
                                'monthly_amounts' => $li->monthly_amounts ?? array_fill_keys(range(1,12), null),
                                'sort_order'      => $li->sort_order,
                            ]);
                        return [
                            'id'         => $grp->id,
                            'name'       => $grp->name,
                            'sort_order' => $grp->sort_order,
                            'line_items' => $items,
                        ];
                    });

                return [
                    'id'             => $sec->id,
                    'statement_type' => $sec->statement_type,
                    'section_key'    => $sec->section_key,
                    'display_name'   => $sec->display_name,
                    'is_computed'    => $sec->is_computed,
                    'computed_from'  => $sec->computed_from,
                    'sort_order'     => $sec->sort_order,
                    'groups'         => $groups,
                ];
            });

        // Load existing sales directors for this budget
        $existingDirectors = DB::table('budget_sales_directors')
            ->where('budget_statement_id', $budget->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($d) => [
                'id'       => $d->id,
                'user_id'  => $d->user_id,
                'name'     => $d->name,
                'title'    => $d->title,
            ]);

        // Load existing line-item assignments: line_item_id => director_id
        $existingAssignments = DB::table('budget_line_item_assignments')
            ->join('budget_sales_directors', 'budget_line_item_assignments.budget_sales_director_id', '=', 'budget_sales_directors.id')
            ->where('budget_sales_directors.budget_statement_id', $budget->id)
            ->pluck('budget_sales_directors.id', 'budget_line_item_assignments.budget_line_item_id')
            ->toArray();

        $orgUsers = \App\Models\User::where('organization_id', $company->organization_id)
            ->orderBy('name')
            ->get()
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return Inertia::render('Budgets/Create', [
            'company'              => [
                'id'       => $company->id,
                'name'     => $company->name,
                'currency' => $company->invested_currency ?? 'USD',
            ],
            'budget'               => [
                'id'     => $budget->id,
                'name'   => $budget->name,
                'year'   => $budget->year,
                'status' => $budget->status,
                'notes'  => $budget->notes,
            ],
            'existingSections'     => $sections,
            'existingDirectors'    => $existingDirectors,
            'existingAssignments'  => $existingAssignments,
            'incomeSections'       => $this->incomeSections(),
            'balanceSections'      => $this->balanceSections(),
            'cashflowSections'     => $this->cashflowSections(),
            'currentYear'          => (int) now()->format('Y'),
            'orgUsers'             => $orgUsers,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // UPDATE  PUT /portfolio-companies/{company}/budgets/{budget}
    // ─────────────────────────────────────────────────────────────────────
    public function update(Request $request, PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $request->validate([
            'name'                  => 'required|string|max:255',
            'year'                  => 'required|integer|min:2000|max:2100',
            'status'                => 'required|in:draft,final',
            'notes'                 => 'nullable|string|max:2000',
            'sections'              => 'required|array',
            'sales_directors'       => 'nullable|array',
            'line_item_assignments' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $budget) {
            $budget->update([
                'name'   => $request->name,
                'year'   => $request->year,
                'status' => $request->status,
                'notes'  => $request->notes,
            ]);

            // Delete all sections (cascades to groups → line items → actuals)
            BudgetSection::where('budget_statement_id', $budget->id)->delete();

            $lineItemKeyMap = $this->saveSections($budget, $request->sections);

            // Re-save directors (delete old, re-create)
            DB::table('budget_sales_directors')->where('budget_statement_id', $budget->id)->delete();
            $this->saveSalesDirectors($budget, $request->sales_directors ?? [], $request->line_item_assignments ?? [], $lineItemKeyMap);
        });

        return redirect()
            ->route('budgets.index', $budget->portfolio_company_id)
            ->with('success', 'Budget statement updated successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // DESTROY  DELETE /portfolio-companies/{company}/budgets/{budget}
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $budget->delete();

        return back()->with('success', 'Budget statement deleted.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // VARIANCE DASHBOARD  GET /portfolio-companies/{company}/budgets/{budget}
    // ─────────────────────────────────────────────────────────────────────
    public function show(PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $data = $this->buildVarianceData($budget);

        // Load directors list for the top nav
        $directors = DB::table('budget_sales_directors')
            ->where('budget_statement_id', $budget->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'title' => $d->title]);

        return Inertia::render('Budgets/Variance', [
            'company'   => ['id' => $company->id, 'name' => $company->name],
            'budget'    => [
                'id'       => $budget->id,
                'name'     => $budget->name,
                'year'     => $budget->year,
                'currency' => $budget->currency,
                'status'   => $budget->status,
            ],
            'data'      => $data,
            'months'    => $this->monthLabels($budget->year),
            'directors' => $directors,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ACTUALS ENTRY  GET /portfolio-companies/{company}/budgets/{budget}/actuals
    // ─────────────────────────────────────────────────────────────────────
    public function actuals(PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        // Load available financial statements for FS import option
        $availableStatements = FinancialStatement::where('portfolio_company_id', $company->id)
            ->orderByDesc('period_to')
            ->get()
            ->map(fn($s) => [
                'id'    => $s->id,
                'label' => $s->period_from->format('M Y') . ' — ' . $s->period_to->format('M Y'),
            ]);

        $data = $this->buildVarianceData($budget);

        return Inertia::render('Budgets/Actuals', [
            'company'    => ['id' => $company->id, 'name' => $company->name],
            'budget'     => [
                'id'       => $budget->id,
                'name'     => $budget->name,
                'year'     => $budget->year,
                'currency' => $budget->currency,
                'status'   => $budget->status,
            ],
            'data'       => $data,
            'months'     => $this->monthLabels($budget->year),
            'statements' => $availableStatements,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SAVE ACTUALS  POST /portfolio-companies/{company}/budgets/{budget}/actuals
    // ─────────────────────────────────────────────────────────────────────
    public function saveActuals(Request $request, PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $request->validate([
            'actuals'                => 'required|array',
            'actuals.*.line_item_id' => 'required|integer',
            'actuals.*.monthly'      => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->actuals as $entry) {
                $monthly = [];
                for ($m = 1; $m <= 12; $m++) {
                    // Vue sends integer keys; handle both just in case
                    $val = $entry['monthly'][$m] ?? $entry['monthly'][(string)$m] ?? null;
                    $monthly[$m] = ($val !== null && $val !== '') ? (float) $val : null;
                }
                BudgetActual::updateOrCreate(
                    ['budget_line_item_id' => $entry['line_item_id']],
                    ['monthly_actuals' => $monthly, 'source' => $entry['source'] ?? 'manual']
                );
            }
        });

        return back()->with('success', 'Actuals saved successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // IMPORT ACTUALS FROM FS  POST .../budgets/{budget}/actuals/import
    // ─────────────────────────────────────────────────────────────────────
    public function importActuals(Request $request, PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);

        $request->validate([
            'statement_id' => 'required|exists:financial_statements,id',
            'month'        => 'required|integer|min:1|max:12',
        ]);

        $statement = FinancialStatement::findOrFail($request->statement_id);
        $sections  = FsSection::where('financial_statement_id', $statement->id)
            ->with('lineItems')
            ->get();

        $fsTotals = $this->buildFsTotals($sections);

        $mapped = [];
        foreach ($fsTotals as $key => $value) {
            $mapped[$key] = $value;
        }

        return response()->json([
            'month'    => (int) $request->month,
            'totals'   => $mapped,
            'fs_label' => $statement->period_from->format('M Y') . ' — ' . $statement->period_to->format('M Y'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ── SALES DIRECTOR REVIEW ROOM ────────────────────────────────────────
    // GET /portfolio-companies/{company}/budgets/{budget}/directors/{director}/review
    // ─────────────────────────────────────────────────────────────────────
    public function directorReview(PortfolioCompany $company, BudgetStatement $budget, $directorId)
    {
        $user = auth()->user();
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $director = DB::table('budget_sales_directors')
            ->where('id', $directorId)
            ->where('budget_statement_id', $budget->id)
            ->first();

        abort_if(!$director, 404);

        // Security: non-admin users can only see their own review room
        if (!$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            abort_if($director->user_id !== $user->id, 403);
        }

        // Load this director's assigned line items (sales_revenue section only)
        $assignedItems = DB::table('budget_line_item_assignments')
            ->join('budget_line_items', 'budget_line_item_assignments.budget_line_item_id', '=', 'budget_line_items.id')
            ->join('budget_groups', 'budget_line_items.budget_group_id', '=', 'budget_groups.id')
            ->join('budget_sections', 'budget_groups.budget_section_id', '=', 'budget_sections.id')
            ->where('budget_line_item_assignments.budget_sales_director_id', $directorId)
            ->where('budget_sections.section_key', 'sales_revenue')
            ->select(
                'budget_line_items.id',
                'budget_line_items.label',
                'budget_line_items.monthly_amounts',
                'budget_groups.name as group_name'
            )
            ->get()
            ->map(function ($item) {
                $monthly = json_decode($item->monthly_amounts, true) ?? array_fill_keys(range(1,12), null);
                // Fetch actuals — monthly_actuals is a JSON col, MySQL returns string keys.
                // Normalise to integer keys so $actuals[$m] always works.
                $actual = BudgetActual::where('budget_line_item_id', $item->id)->first();
                // monthly_actuals may be a raw JSON string (no model cast) or already an array
                $raw = $actual ? ($actual->monthly_actuals ?? []) : [];
                if (is_string($raw)) $raw = json_decode($raw, true) ?? [];
                $actuals = [];
                for ($m = 1; $m <= 12; $m++) {
                    $actuals[$m] = $raw[$m] ?? $raw[(string)$m] ?? null;
                }

                // Also normalise monthly_budget string keys (from monthly_amounts JSON)
                $monthlyNorm = [];
                for ($m = 1; $m <= 12; $m++) {
                    $monthlyNorm[$m] = $monthly[$m] ?? $monthly[(string)$m] ?? null;
                }
                $monthly = $monthlyNorm;

                // Build variance
                $variance = [];
                for ($m = 1; $m <= 12; $m++) {
                    $b = $monthly[$m] ?? 0;
                    $a = $actuals[$m];
                    $variance[$m] = ($a !== null) ? ($a - $b) : null;
                }

                return [
                    'id'              => $item->id,
                    'label'           => $item->label,
                    'group_name'      => $item->group_name,
                    'monthly_budget'  => $monthly,
                    'monthly_actual'  => $actuals,
                    'monthly_variance'=> $variance,
                ];
            });

        // Load existing review entries for this director
        $reviews = DB::table('budget_director_reviews')
            ->where('budget_sales_director_id', $directorId)
            ->get()
            ->keyBy('month')
            ->map(fn($r) => [
                'month'            => $r->month,
                'variance_comment' => $r->variance_comment,
                'action_taken'     => $r->action_taken,
                'pipeline_amount'  => (float)($r->pipeline_amount ?? 0),
                // pipeline_notes stores a JSON array of {name, amount} items
                'pipeline_items'   => json_decode($r->pipeline_notes ?? '[]', true) ?: [],
                'prospects_amount' => (float)($r->prospects_amount ?? 0),
                'prospects_items'  => json_decode($r->prospects_notes ?? '[]', true) ?: [],
                'priority'         => $r->priority ?? 'medium',
            ]);

        // Build summary totals across all assigned items
        $summaryBudget  = array_fill_keys(range(1,12), 0.0);
        $summaryActual  = array_fill_keys(range(1,12), 0.0);
        foreach ($assignedItems as $item) {
            for ($m = 1; $m <= 12; $m++) {
                $summaryBudget[$m] += (float)($item['monthly_budget'][$m] ?? $item['monthly_budget'][(string)$m] ?? 0);
                $summaryActual[$m] += (float)($item['monthly_actual'][$m] ?? $item['monthly_actual'][(string)$m] ?? 0);
            }
        }
        $summaryVariance = [];
        for ($m = 1; $m <= 12; $m++) {
            $summaryVariance[$m] = $summaryActual[$m] - $summaryBudget[$m];
        }

        return Inertia::render('Budgets/DirectorReview', [
            'company'    => ['id' => $company->id, 'name' => $company->name],
            'budget'     => [
                'id'       => $budget->id,
                'name'     => $budget->name,
                'year'     => $budget->year,
                'currency' => $budget->currency,
            ],
            'director'       => [
                'id'    => $director->id,
                'name'  => $director->name,
                'title' => $director->title,
            ],
            'assignedItems'  => $assignedItems->values(),
            'reviews'        => $reviews,
            'summaryBudget'  => $summaryBudget,
            'summaryActual'  => $summaryActual,
            'summaryVariance'=> $summaryVariance,
            'months'         => $this->monthLabels($budget->year),
            'isAdmin'        => $user->hasRole('super-admin') || $user->hasRole('admin'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SAVE DIRECTOR REVIEW  POST .../{director}/review/save
    // ─────────────────────────────────────────────────────────────────────
    public function saveDirectorReview(Request $request, PortfolioCompany $company, BudgetStatement $budget, $directorId)
    {
        $user = auth()->user();
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $director = DB::table('budget_sales_directors')
            ->where('id', $directorId)
            ->where('budget_statement_id', $budget->id)
            ->first();

        abort_if(!$director, 404);

        // Only the director themselves OR admins can save
        if (!$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            abort_if($director->user_id !== $user->id, 403);
        }

        $request->validate([
            'month'                       => 'required|integer|min:1|max:12',
            'variance_comment'            => 'nullable|string|max:3000',
            'action_taken'                => 'nullable|string|max:3000',
            'pipeline_items'              => 'nullable|array',
            'pipeline_items.*.name'       => 'nullable|string|max:500',
            'pipeline_items.*.amount'     => 'nullable|numeric|min:0',
            'prospects_items'             => 'nullable|array',
            'prospects_items.*.name'      => 'nullable|string|max:500',
            'prospects_items.*.amount'    => 'nullable|numeric|min:0',
            'priority'                    => 'nullable|in:low,medium,high',
        ]);

        // Compute totals from repeater rows
        $pipelineItems   = collect($request->pipeline_items ?? [])
            ->filter(fn($i) => !empty($i['name']) || !empty($i['amount']))
            ->values()->toArray();
        $prospectsItems  = collect($request->prospects_items ?? [])
            ->filter(fn($i) => !empty($i['name']) || !empty($i['amount']))
            ->values()->toArray();
        $pipelineTotal   = collect($pipelineItems)->sum(fn($i) => (float)($i['amount'] ?? 0));
        $prospectsTotal  = collect($prospectsItems)->sum(fn($i) => (float)($i['amount'] ?? 0));

        DB::table('budget_director_reviews')->updateOrInsert(
            [
                'budget_sales_director_id' => $directorId,
                'month'                    => $request->month,
            ],
            [
                'variance_comment'  => $request->variance_comment,
                'action_taken'      => $request->action_taken,
                'pipeline_amount'   => $pipelineTotal,
                'pipeline_notes'    => json_encode($pipelineItems),
                'prospects_amount'  => $prospectsTotal,
                'prospects_notes'   => json_encode($prospectsItems),
                'priority'          => $request->priority ?? 'medium',
                'saved_by'          => $user->id,
                'updated_at'        => now(),
                'created_at'        => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LIST DIRECTORS  GET .../budgets/{budget}/directors
    // Used by Variance page to list all directors for navigation
    // ─────────────────────────────────────────────────────────────────────
    public function directors(PortfolioCompany $company, BudgetStatement $budget)
    {
        $this->getCompany($company->id);
        abort_if($budget->portfolio_company_id !== $company->id, 404);

        $directors = DB::table('budget_sales_directors')
            ->where('budget_statement_id', $budget->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($d) => [
                'id'    => $d->id,
                'name'  => $d->name,
                'title' => $d->title,
            ]);

        return Inertia::render('Budgets/DirectorsList', [
            'company'   => ['id' => $company->id, 'name' => $company->name],
            'budget'    => [
                'id'       => $budget->id,
                'name'     => $budget->name,
                'year'     => $budget->year,
                'currency' => $budget->currency,
            ],
            'directors' => $directors,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE: save sales directors + line-item assignments
    // ─────────────────────────────────────────────────────────────────────
    private function saveSalesDirectors(BudgetStatement $budget, array $directors, array $assignments, array $lineItemKeyMap = []): void
    {
        // $directors = [{ user_id, name, title }, ...]
        // $assignments = { "g0_i0" => director_index, ... }  (path-key => index)
        // $lineItemKeyMap = { "g0_i0" => db_line_item_id, ... }

        $directorIdMap = [];  // index → new DB id

        foreach ($directors as $idx => $dir) {
            if (empty($dir['name'])) continue;

            $row = DB::table('budget_sales_directors')->insertGetId([
                'budget_statement_id' => $budget->id,
                'user_id'             => $dir['user_id'] ?? 0,
                'name'                => $dir['name'],
                'title'               => $dir['title'] ?? null,
                'sort_order'          => $idx,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $directorIdMap[$idx] = $row;
        }

        // Resolve assignments: path-key → DB line item id → director DB id
        foreach ($assignments as $pathKey => $directorIdx) {
            $directorIdx = (int) $directorIdx;
            if (!isset($directorIdMap[$directorIdx])) continue;

            // Resolve line item DB id from path-key (g0_i0 → DB id via lineItemKeyMap)
            $lineItemId = $lineItemKeyMap[$pathKey] ?? null;
            if (!$lineItemId) continue;

            DB::table('budget_line_item_assignments')->updateOrInsert(
                ['budget_line_item_id' => $lineItemId],
                [
                    'budget_sales_director_id' => $directorIdMap[$directorIdx],
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE: save sections → groups → line items
    // ─────────────────────────────────────────────────────────────────────
    /**
     * Save sections → groups → line items.
     * Returns a map of path-keys to DB IDs for sales_revenue line items:
     *   [ "g0_i0" => 42, "g0_i1" => 43, ... ]
     * This is passed to saveSalesDirectors() to resolve assignments.
     */
    private function saveSections(BudgetStatement $budget, array $sectionsPayload): array
    {
        $lineItemKeyMap = [];  // "g{gi}_i{li}" => db_id (sales_revenue only)

        foreach ($this->allSectionTemplates() as $template) {
            $key  = $template['key'];
            $type = $template['type'];

            $section = BudgetSection::create([
                'budget_statement_id' => $budget->id,
                'statement_type'      => $type,
                'section_key'         => $key,
                'display_name'        => $template['label'],
                'is_computed'         => $template['computed'],
                'computed_from'       => $template['from'] ? json_encode($template['from']) : null,
                'sort_order'          => $template['sort'],
            ]);

            if ($template['computed']) continue;

            $groups = $sectionsPayload[$key]['groups'] ?? [];

            foreach ($groups as $gi => $grp) {
                if (empty(trim($grp['name'] ?? ''))) continue;

                $group = BudgetGroup::create([
                    'budget_section_id' => $section->id,
                    'name'              => $grp['name'],
                    'sort_order'        => $gi,
                ]);

                foreach ($grp['line_items'] ?? [] as $li => $item) {
                    if (empty(trim($item['label'] ?? ''))) continue;

                    $monthly = [];
                    for ($m = 1; $m <= 12; $m++) {
                        $val = $item['monthly_amounts'][$m] ?? null;
                        $monthly[$m] = ($val !== null && $val !== '') ? (float) $val : null;
                    }

                    $lineItem = BudgetLineItem::create([
                        'budget_group_id' => $group->id,
                        'label'           => $item['label'],
                        'monthly_amounts' => $monthly,
                        'sort_order'      => $li,
                    ]);

                    // Track path-key → DB id for sales_revenue assignments
                    if ($key === 'sales_revenue') {
                        $lineItemKeyMap["g{$gi}_i{$li}"] = $lineItem->id;
                    }
                }
            }
        }

        return $lineItemKeyMap;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE: build full variance data structure
    // ─────────────────────────────────────────────────────────────────────
    private function buildVarianceData(BudgetStatement $budget): array
    {
        $sections = BudgetSection::where('budget_statement_id', $budget->id)
            ->orderBy('sort_order')
            ->get();

        $result = [];

        foreach ($sections as $sec) {
            $groups = BudgetGroup::where('budget_section_id', $sec->id)
                ->orderBy('sort_order')
                ->get();

            $sectionBudget = array_fill_keys(range(1,12), 0.0);
            $sectionActual = array_fill_keys(range(1,12), 0.0);

            $groupsData = $groups->map(function ($grp) use (&$sectionBudget, &$sectionActual) {
                $items = BudgetLineItem::where('budget_group_id', $grp->id)
                    ->orderBy('sort_order')
                    ->get();

                $groupBudget = array_fill_keys(range(1,12), 0.0);
                $groupActual = array_fill_keys(range(1,12), 0.0);

                $itemsData = $items->map(function ($item) use (&$groupBudget, &$groupActual) {
                    $actual = BudgetActual::where('budget_line_item_id', $item->id)->first();

                    // monthly_amounts and monthly_actuals may be raw JSON strings if model has no cast
                    $bRaw = $item->monthly_amounts ?? [];
                    if (is_string($bRaw)) $bRaw = json_decode($bRaw, true) ?? [];
                    $bMonthly = [];
                    for ($mx = 1; $mx <= 12; $mx++) {
                        $bMonthly[$mx] = $bRaw[$mx] ?? $bRaw[(string)$mx] ?? null;
                    }

                    $aRaw = $actual ? ($actual->monthly_actuals ?? []) : [];
                    if (is_string($aRaw)) $aRaw = json_decode($aRaw, true) ?? [];
                    $aMonthly = [];
                    for ($mx = 1; $mx <= 12; $mx++) {
                        $aMonthly[$mx] = $aRaw[$mx] ?? $aRaw[(string)$mx] ?? null;
                    }

                    $variance     = [];
                    $cumBudget    = [];
                    $cumActual    = [];
                    $cumVariance  = [];
                    $runB = 0; $runA = 0;

                    for ($m = 1; $m <= 12; $m++) {
                        $b = $bMonthly[$m] ?? 0;
                        $a = $aMonthly[$m] ?? null;

                        $variance[$m] = ($a !== null) ? ($a - $b) : null;

                        $runB += $b;
                        if ($a !== null) $runA += $a;

                        $cumBudget[$m]   = $runB;
                        $cumActual[$m]   = ($a !== null) ? $runA : null;
                        $cumVariance[$m] = ($a !== null) ? ($runA - $runB) : null;
                    }

                    for ($m = 1; $m <= 12; $m++) {
                        $groupBudget[$m] += ($bMonthly[$m] ?? 0);
                        $groupActual[$m] += ($aMonthly[$m] ?? 0);
                    }

                    // Load director assignment for this line item (sales_revenue only)
                    $assignment = DB::table('budget_line_item_assignments')
                        ->join('budget_sales_directors', 'budget_line_item_assignments.budget_sales_director_id', '=', 'budget_sales_directors.id')
                        ->where('budget_line_item_assignments.budget_line_item_id', $item->id)
                        ->select('budget_sales_directors.id as director_id', 'budget_sales_directors.name as director_name')
                        ->first();

                    return [
                        'id'                  => $item->id,
                        'label'               => $item->label,
                        'monthly_budget'      => $bMonthly,
                        'monthly_actual'      => $aMonthly,
                        'monthly_variance'    => $variance,
                        'cumulative_budget'   => $cumBudget,
                        'cumulative_actual'   => $cumActual,
                        'cumulative_variance' => $cumVariance,
                        'source'              => $actual?->source ?? 'manual',
                        'director_id'         => $assignment?->director_id,
                        'director_name'       => $assignment?->director_name,
                    ];
                })->values();

                for ($m = 1; $m <= 12; $m++) {
                    $sectionBudget[$m] += $groupBudget[$m];
                    $sectionActual[$m] += $groupActual[$m];
                }

                $grpVariance = $grpCumBudget = $grpCumActual = $grpCumVariance = [];
                $grpCumB = $grpCumA = 0;

                for ($m = 1; $m <= 12; $m++) {
                    $grpVariance[$m] = $groupActual[$m] - $groupBudget[$m];
                    $grpCumB += $groupBudget[$m];
                    $grpCumA += $groupActual[$m];
                    $grpCumBudget[$m]   = $grpCumB;
                    $grpCumActual[$m]   = $grpCumA;
                    $grpCumVariance[$m] = $grpCumA - $grpCumB;
                }

                return [
                    'id'                  => $grp->id,
                    'name'                => $grp->name,
                    'line_items'          => $itemsData,
                    'monthly_budget'      => $groupBudget,
                    'monthly_actual'      => $groupActual,
                    'monthly_variance'    => $grpVariance,
                    'cumulative_budget'   => $grpCumBudget,
                    'cumulative_actual'   => $grpCumActual,
                    'cumulative_variance' => $grpCumVariance,
                ];
            })->values();

            $secVariance = $secCumBudget = $secCumActual = $secCumVariance = [];
            $secCumB = $secCumA = 0;

            for ($m = 1; $m <= 12; $m++) {
                $secVariance[$m] = $sectionActual[$m] - $sectionBudget[$m];
                $secCumB += $sectionBudget[$m];
                $secCumA += $sectionActual[$m];
                $secCumBudget[$m]   = $secCumB;
                $secCumActual[$m]   = $secCumA;
                $secCumVariance[$m] = $secCumA - $secCumB;
            }

            $result[] = [
                'id'                  => $sec->id,
                'section_key'         => $sec->section_key,
                'display_name'        => $sec->display_name,
                'statement_type'      => $sec->statement_type,
                'is_computed'         => $sec->is_computed,
                'computed_from'       => $sec->computed_from,
                'sort_order'          => $sec->sort_order,
                'groups'              => $groupsData,
                'monthly_budget'      => $sectionBudget,
                'monthly_actual'      => $sectionActual,
                'monthly_variance'    => $secVariance,
                'cumulative_budget'   => $secCumBudget,
                'cumulative_actual'   => $secCumActual,
                'cumulative_variance' => $secCumVariance,
            ];
        }

        // Second pass: resolve computed section totals
        $sectionTotalsBudget = [];
        $sectionTotalsActual = [];
        foreach ($result as $sec) {
            $sectionTotalsBudget[$sec['section_key']] = $sec['monthly_budget'];
            $sectionTotalsActual[$sec['section_key']] = $sec['monthly_actual'];
        }

        foreach ($result as &$sec) {
            if (!$sec['is_computed'] || !$sec['computed_from']) continue;

            $formula = $sec['computed_from'];
            if (is_string($formula)) $formula = json_decode($formula, true) ?? [];

            $compBudget = array_fill_keys(range(1,12), 0.0);
            $compActual = array_fill_keys(range(1,12), 0.0);

            foreach ($formula as $part) {
                $k = $part['key']; $sign = $part['sign'];
                for ($m = 1; $m <= 12; $m++) {
                    $compBudget[$m] += ($sectionTotalsBudget[$k][$m] ?? 0) * $sign;
                    $compActual[$m] += ($sectionTotalsActual[$k][$m] ?? 0) * $sign;
                }
            }

            $compVariance = $compCumBudget = $compCumActual = $compCumVariance = [];
            $compCumB = $compCumA = 0;

            for ($m = 1; $m <= 12; $m++) {
                $compVariance[$m] = $compActual[$m] - $compBudget[$m];
                $compCumB += $compBudget[$m];
                $compCumA += $compActual[$m];
                $compCumBudget[$m]   = $compCumB;
                $compCumActual[$m]   = $compCumA;
                $compCumVariance[$m] = $compCumA - $compCumB;
            }

            $sec['monthly_budget']      = $compBudget;
            $sec['monthly_actual']      = $compActual;
            $sec['monthly_variance']    = $compVariance;
            $sec['cumulative_budget']   = $compCumBudget;
            $sec['cumulative_actual']   = $compCumActual;
            $sec['cumulative_variance'] = $compCumVariance;

            $sectionTotalsBudget[$sec['section_key']] = $compBudget;
            $sectionTotalsActual[$sec['section_key']] = $compActual;
        }

        return [
            'income'        => array_values(array_filter($result, fn($s) => $s['statement_type'] === 'income')),
            'balance_sheet' => array_values(array_filter($result, fn($s) => $s['statement_type'] === 'balance_sheet')),
            'cashflow'      => array_values(array_filter($result, fn($s) => $s['statement_type'] === 'cashflow')),
        ];
    }

    private function buildFsTotals($sections): array
    {
        $totals = [];
        foreach ($sections as $sec) {
            if (!$sec->is_computed) {
                $totals[$sec->section_key] = (float) $sec->lineItems->sum('amount');
            }
        }
        for ($pass = 0; $pass < 10; $pass++) {
            foreach ($sections as $sec) {
                if (!$sec->is_computed || !$sec->computed_from) continue;
                $formula = $sec->computed_from;
                if (is_string($formula)) $formula = json_decode($formula, true) ?? [];
                if (!is_array($formula)) continue;
                $val = 0; $ok = true;
                foreach ($formula as $part) {
                    if (!array_key_exists($part['key'], $totals)) { $ok = false; break; }
                    $val += $totals[$part['key']] * $part['sign'];
                }
                if ($ok) $totals[$sec->section_key] = $val;
            }
        }
        return $totals;
    }

    private function monthLabels(int $year): array
    {
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[$m] = \Carbon\Carbon::create($year, $m, 1)->format('M');
        }
        return $labels;
    }
}