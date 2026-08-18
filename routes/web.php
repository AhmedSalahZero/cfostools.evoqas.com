<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PortfolioCompanyController;
use App\Http\Controllers\FinancialStudyController;
use App\Http\Controllers\OpeningBalanceController;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');


// ── Profile ──────────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// ── Super-Admin only ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:super-admin'])->group(function () {
    Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::post('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
});


// ── Admin & Super-Admin ───────────────────────────────────────────────────────
Route::middleware(['auth', 'role:super-admin|admin'])->group(function () {

// Portfolio Companies CRUD (create/delete — admin only)
    Route::get('/portfolio-companies/create', [PortfolioCompanyController::class, 'create'])->name('portfolio-companies.create');
    Route::post('/portfolio-companies', [PortfolioCompanyController::class, 'store'])->name('portfolio-companies.store');
    Route::delete('/portfolio-companies/{company}', [PortfolioCompanyController::class, 'destroy'])->name('portfolio-companies.destroy');

    // Customer Contracts CRUD (admin/super-admin)
    Route::get('/portfolio-companies/{portfolioCompany}/contracts/create', [\App\Http\Controllers\CustomerContractController::class, 'create'])->name('customer-contracts.create');
    Route::post('/portfolio-companies/{portfolioCompany}/contracts', [\App\Http\Controllers\CustomerContractController::class, 'store'])->name('customer-contracts.store');
    Route::get('/portfolio-companies/{portfolioCompany}/contracts/{contract}/edit', [\App\Http\Controllers\CustomerContractController::class, 'edit'])->name('customer-contracts.edit');
    Route::put('/portfolio-companies/{portfolioCompany}/contracts/{contract}', [\App\Http\Controllers\CustomerContractController::class, 'update'])->name('customer-contracts.update');
    Route::delete('/portfolio-companies/{portfolioCompany}/contracts/{contract}', [\App\Http\Controllers\CustomerContractController::class, 'destroy'])->name('customer-contracts.destroy');
    Route::put('/portfolio-companies/{portfolioCompany}/contracts/{contract}/mark-running', [\App\Http\Controllers\CustomerContractController::class, 'markRunning'])->name('customer-contracts.mark-running');
    Route::put('/portfolio-companies/{portfolioCompany}/contracts/{contract}/mark-finished', [\App\Http\Controllers\CustomerContractController::class, 'markFinished'])->name('customer-contracts.mark-finished');

    // User Management
    Route::get('/users', [App\Http\Controllers\UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [App\Http\Controllers\UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [App\Http\Controllers\UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [App\Http\Controllers\UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [App\Http\Controllers\UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [App\Http\Controllers\UserManagementController::class, 'destroy'])->name('users.destroy');

});

// ── Public Survey Routes (NO auth required) ───────────────────────────────────
Route::get('/s/{token}',  [App\Http\Controllers\SurveyController::class, 'publicShow'])->name('survey.public');
Route::post('/s/{token}', [App\Http\Controllers\SurveyController::class, 'publicSubmit'])->name('survey.public.submit');


// ── All authenticated users ───────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // ── InvestaDocs — org-level deal desk ─────────────────────────────────────
    Route::prefix('organizations/{orgId}/investadocs')
        ->name('investadocs.')
        ->middleware('role:super-admin|admin')
        ->group(function () {
            Route::get('/',                       [App\Http\Controllers\InvestaDocsController::class, 'index'])->name('index');
            Route::get('/create',                 [App\Http\Controllers\InvestaDocsController::class, 'create'])->name('create');
            Route::post('/',                      [App\Http\Controllers\InvestaDocsController::class, 'store'])->name('store');
            Route::get('/{docId}/download',       [App\Http\Controllers\InvestaDocsController::class, 'download'])->name('download');
            Route::patch('/{docId}/status',       [App\Http\Controllers\InvestaDocsController::class, 'updateStatus'])->name('update-status');
            Route::patch('/{docId}/link-company', [App\Http\Controllers\InvestaDocsController::class, 'linkCompany'])->name('link-company');
            Route::delete('/{docId}',             [App\Http\Controllers\InvestaDocsController::class, 'destroy'])->name('destroy');
            Route::get('/{docId}',                [App\Http\Controllers\InvestaDocsController::class, 'show'])->name('show');
        });

    // Portfolio Companies index & show
    Route::get('/portfolio-companies', [PortfolioCompanyController::class, 'index'])->name('portfolio-companies.index');
    Route::get('/portfolio-companies/{company}', [PortfolioCompanyController::class, 'show'])->middleware('company.access:view_company')->name('portfolio-companies.show');
    Route::get('/portfolio-companies/{company}/edit', [PortfolioCompanyController::class, 'edit'])->name('portfolio-companies.edit');
    Route::put('/portfolio-companies/{company}', [PortfolioCompanyController::class, 'update'])->name('portfolio-companies.update');

    // Customer Contracts (read — all auth users)
    Route::get('/portfolio-companies/{portfolioCompany}/contracts', [\App\Http\Controllers\CustomerContractController::class, 'index'])->name('customer-contracts.index');

    // Customer Contracts CRUD (admin only — wrapped below)

    // ── Dashboard Company Notes ───────────────────────────────────────────────
    Route::get('/dashboard/company-notes/{company}', [App\Http\Controllers\CompanyNoteController::class, 'index'])->name('dashboard.notes.get');
    Route::post('/dashboard/company-notes/{company}', [App\Http\Controllers\CompanyNoteController::class, 'store'])->name('dashboard.notes.store');
    Route::delete('/dashboard/company-notes/{company}/{note}', [App\Http\Controllers\CompanyNoteController::class, 'destroy'])->name('dashboard.notes.delete');

    // ── Sales Analysis ──────────────────────────────────────────────────────
    Route::get('/companies/{company}/sales/field-mapping', [App\Http\Controllers\SalesAnalysisController::class, 'fieldMapping'])->name('sales.field-mapping');
    Route::post('/companies/{company}/sales/field-mapping', [App\Http\Controllers\SalesAnalysisController::class, 'saveFieldMapping'])->name('sales.save-field-mapping');
    Route::get('/companies/{company}/sales/download-template', [App\Http\Controllers\SalesAnalysisController::class, 'downloadTemplate'])->name('sales.download-template');
    Route::get('/companies/{company}/sales/upload', [App\Http\Controllers\SalesAnalysisController::class, 'uploadPage'])->name('sales.upload');
    Route::post('/companies/{company}/sales/upload', [App\Http\Controllers\SalesAnalysisController::class, 'processUpload'])->name('sales.process-upload');
    Route::get('/companies/{company}/sales/reports', [App\Http\Controllers\SalesAnalysisController::class, 'reportsPage'])->name('sales.reports');
    Route::post('/companies/{company}/sales/reports/run', [App\Http\Controllers\SalesAnalysisController::class, 'runReport'])->name('sales.run-report');
    Route::post('/companies/{company}/sales/export-report', [App\Http\Controllers\SalesAnalysisController::class, 'exportReport'])->name('sales.export-report');
    Route::get('/companies/{company}/sales', [App\Http\Controllers\SalesDashboardController::class, 'dashboardPage'])->name('sales.dashboard');
    Route::get('/companies/{company}/sales/dashboard-data', [App\Http\Controllers\SalesDashboardController::class, 'dashboardData'])->name('sales.dashboard-data');
    Route::get('/companies/{company}/sales/takeaway', [App\Http\Controllers\SalesDashboardController::class, 'takeaway'])->name('sales.takeaway');
    Route::post('/companies/{company}/sales/save-note', [App\Http\Controllers\SalesDashboardController::class, 'saveNote'])->name('sales.save-note');
    Route::get('/companies/{company}/sales/get-notes', [App\Http\Controllers\SalesDashboardController::class, 'getNotes'])->name('sales.get-notes');
    Route::put('/companies/{company}/sales/notes/{note}', [App\Http\Controllers\SalesDashboardController::class, 'updateNote'])->name('sales.update-note');
    Route::delete('/companies/{company}/sales/notes/{note}', [App\Http\Controllers\SalesDashboardController::class, 'deleteNote'])->name('sales.delete-note');
    Route::delete('/companies/{company}/sales/uploads/{upload}', [App\Http\Controllers\SalesAnalysisController::class, 'deleteUpload'])->name('sales.delete-upload');

   
   // ────────────────────────────────────────────────────────────────────────────
// ADD THIS BLOCK to web.php inside Route::middleware(['auth'])->group(...)
// Place it right after the Sales Analysis routes block
// ────────────────────────────────────────────────────────────────────────────

    // ── Export Sales Analysis ─────────────────────────────────────────────
    Route::get('/companies/{company}/export-sales/field-mapping',   [App\Http\Controllers\ExportSalesAnalysisController::class, 'fieldMapping'])->name('export-sales.field-mapping');
    Route::post('/companies/{company}/export-sales/field-mapping',  [App\Http\Controllers\ExportSalesAnalysisController::class, 'saveFieldMapping'])->name('export-sales.save-field-mapping');
    Route::get('/companies/{company}/export-sales/download-template',[App\Http\Controllers\ExportSalesAnalysisController::class, 'downloadTemplate'])->name('export-sales.download-template');
    Route::get('/companies/{company}/export-sales/upload',          [App\Http\Controllers\ExportSalesAnalysisController::class, 'uploadPage'])->name('export-sales.upload');
    Route::post('/companies/{company}/export-sales/upload',         [App\Http\Controllers\ExportSalesAnalysisController::class, 'processUpload'])->name('export-sales.process-upload');
    Route::get('/companies/{company}/export-sales/reports',         [App\Http\Controllers\ExportSalesAnalysisController::class, 'reportsPage'])->name('export-sales.reports');
    Route::post('/companies/{company}/export-sales/reports/run',    [App\Http\Controllers\ExportSalesAnalysisController::class, 'runReport'])->name('export-sales.run-report');
    Route::post('/companies/{company}/export-sales/export-report',  [App\Http\Controllers\ExportSalesAnalysisController::class, 'exportReport'])->name('export-sales.export-report');
    Route::delete('/companies/{company}/export-sales/uploads/{upload}', [App\Http\Controllers\ExportSalesAnalysisController::class, 'deleteUpload'])->name('export-sales.delete-upload');
   
    Route::get('/companies/{company}/export-sales',              [App\Http\Controllers\ExportSalesDashboardController::class, 'dashboardPage'])->name('export-sales.dashboard');
    Route::get('/companies/{company}/export-sales/dashboard-data',[App\Http\Controllers\ExportSalesDashboardController::class, 'dashboardData'])->name('export-sales.dashboard-data');
    Route::get('/companies/{company}/export-sales/takeaway',     [App\Http\Controllers\ExportSalesDashboardController::class, 'takeaway'])->name('export-sales.takeaway');
    Route::post('/companies/{company}/export-sales/save-note',   [App\Http\Controllers\ExportSalesDashboardController::class, 'saveNote'])->name('export-sales.save-note');
    Route::get('/companies/{company}/export-sales/get-notes',    [App\Http\Controllers\ExportSalesDashboardController::class, 'getNotes'])->name('export-sales.get-notes');
    Route::put('/companies/{company}/export-sales/notes/{note}', [App\Http\Controllers\ExportSalesDashboardController::class, 'updateNote'])->name('export-sales.update-note');
    Route::delete('/companies/{company}/export-sales/notes/{note}',[App\Http\Controllers\ExportSalesDashboardController::class, 'deleteNote'])->name('export-sales.delete-note');






   
    // ── Expense Analysis ─────────────────────────────────────────────────────
    Route::get('/companies/{company}/expenses/upload',            [App\Http\Controllers\ExpenseAnalysisController::class, 'uploadPage'])->name('expense.upload');
    Route::post('/companies/{company}/expenses/upload',           [App\Http\Controllers\ExpenseAnalysisController::class, 'processUpload'])->name('expense.process-upload');
    Route::get('/companies/{company}/expenses/download-template', [App\Http\Controllers\ExpenseAnalysisController::class, 'downloadTemplate'])->name('expense.download-template');
    Route::get('/companies/{company}/expenses/reports',           [App\Http\Controllers\ExpenseAnalysisController::class, 'reportsPage'])->name('expense.reports');
    Route::post('/companies/{company}/expenses/reports/run',      [App\Http\Controllers\ExpenseAnalysisController::class, 'runReport'])->name('expense.run-report');
    Route::post('/companies/{company}/expenses/export-report',    [App\Http\Controllers\ExpenseAnalysisController::class, 'exportReport'])->name('expense.export-report');
    Route::get('/companies/{company}/expenses',                   [App\Http\Controllers\ExpenseAnalysisController::class, 'dashboardPage'])->name('expense.dashboard');
    Route::get('/companies/{company}/expenses/dashboard-data',    [App\Http\Controllers\ExpenseAnalysisController::class, 'dashboardData'])->name('expense.dashboard-data');
    Route::get('/companies/{company}/expenses/breakeven',              [App\Http\Controllers\ExpenseAnalysisController::class, 'breakevenPage'])->name('expense.breakeven');
    Route::post('/companies/{company}/expenses/breakeven/calculate',   [App\Http\Controllers\ExpenseAnalysisController::class, 'breakevenCalculate'])->name('expense.breakeven-calculate');
    Route::get('/companies/{company}/expenses/export-report',          [App\Http\Controllers\ExpenseAnalysisController::class, 'exportReport'])->name('expense.export-report-get');
    Route::delete('/companies/{company}/expenses/uploads/{upload}',    [App\Http\Controllers\ExpenseAnalysisController::class, 'deleteUpload'])->name('expense.delete-upload');
    Route::get('/companies/{company}/expenses/insights',   [App\Http\Controllers\ExpenseAnalysisController::class, 'insights'])->name('expense.insights');
    Route::post('/companies/{company}/expenses/notes',     [App\Http\Controllers\ExpenseAnalysisController::class, 'saveNote'])->name('expense.save-note');
    Route::get('/companies/{company}/expenses/notes',      [App\Http\Controllers\ExpenseAnalysisController::class, 'getNotes'])->name('expense.get-notes');
    Route::put('/companies/{company}/expenses/notes/{note}',    [App\Http\Controllers\ExpenseAnalysisController::class, 'updateNote'])->name('expense.update-note');
    Route::delete('/companies/{company}/expenses/notes/{note}', [App\Http\Controllers\ExpenseAnalysisController::class, 'deleteNote'])->name('expense.delete-note');

    // ── Profitability ─────────────────────────────────────────────────────────
    Route::get('/companies/{company}/profitability',                [App\Http\Controllers\ProfitabilityController::class, 'dashboardPage'])->name('profitability.dashboard');
    Route::get('/companies/{company}/profitability/data',           [App\Http\Controllers\ProfitabilityController::class, 'dashboardData'])->name('profitability.data');
    Route::get('/companies/{company}/profitability/mapping',        [App\Http\Controllers\ProfitabilityController::class, 'mappingPage'])->name('profitability.mapping');
    Route::post('/companies/{company}/profitability/mappings',      [App\Http\Controllers\ProfitabilityController::class, 'saveMappings'])->name('profitability.save-mappings');
    Route::post('/companies/{company}/profitability/manual-input',  [App\Http\Controllers\ProfitabilityController::class, 'saveManualInput'])->name('profitability.manual-input');
    Route::get('/companies/{company}/profitability/insights',   [App\Http\Controllers\ProfitabilityController::class, 'insights'])->name('profitability.insights');
    Route::post('/companies/{company}/profitability/notes',     [App\Http\Controllers\ProfitabilityController::class, 'saveNote'])->name('profitability.save-note');
    Route::get('/companies/{company}/profitability/notes',      [App\Http\Controllers\ProfitabilityController::class, 'getNotes'])->name('profitability.get-notes');
    Route::put('/companies/{company}/profitability/notes/{note}',    [App\Http\Controllers\ProfitabilityController::class, 'updateNote'])->name('profitability.update-note');
    Route::delete('/companies/{company}/profitability/notes/{note}', [App\Http\Controllers\ProfitabilityController::class, 'deleteNote'])->name('profitability.delete-note');

      
   
    // ── Financial Statements ──────────────────────────────────────────────────
    // IMPORTANT: static word routes BEFORE {statement} wildcard
    Route::get('/portfolio-companies/{company}/financial-statements',                    [App\Http\Controllers\FinancialStatementController::class, 'index'])->name('financial-statements.index');
    Route::get('/portfolio-companies/{company}/financial-statements/create',             [App\Http\Controllers\FinancialStatementController::class, 'create'])->name('financial-statements.create');
    Route::get('/portfolio-companies/{company}/financial-statements/upload',             [App\Http\Controllers\FinancialStatementController::class, 'uploadPage'])->name('financial-statements.upload');
    Route::get('/portfolio-companies/{company}/financial-statements/download-template',  [App\Http\Controllers\FinancialStatementController::class, 'downloadTemplate'])->name('financial-statements.download-template');
    Route::get('/portfolio-companies/{company}/financial-statements/prior-balance',      [App\Http\Controllers\FinancialStatementController::class, 'priorBalance'])->name('financial-statements.prior-balance');
    Route::get('/portfolio-companies/{company}/financial-statements/compare',            [App\Http\Controllers\FinancialStatementController::class, 'compare'])->name('financial-statements.compare');
    Route::post('/portfolio-companies/{company}/financial-statements',                   [App\Http\Controllers\FinancialStatementController::class, 'store'])->name('financial-statements.store');
    Route::post('/portfolio-companies/{company}/financial-statements/upload',            [App\Http\Controllers\FinancialStatementController::class, 'processUpload'])->name('financial-statements.process-upload');
    Route::get('/portfolio-companies/{company}/financial-statements/{statement}',        [App\Http\Controllers\FinancialStatementController::class, 'show'])->name('financial-statements.show');
    Route::get('/portfolio-companies/{company}/financial-statements/{statement}/edit',   [App\Http\Controllers\FinancialStatementController::class, 'edit'])->name('financial-statements.edit');
    Route::get('/portfolio-companies/{company}/financial-statements/{statement}/export', [App\Http\Controllers\FinancialStatementController::class, 'export'])->name('financial-statements.export');
    Route::put('/portfolio-companies/{company}/financial-statements/{statement}',        [App\Http\Controllers\FinancialStatementController::class, 'update'])->name('financial-statements.update');
    Route::delete('/portfolio-companies/{company}/financial-statements/{statement}',     [App\Http\Controllers\FinancialStatementController::class, 'destroy'])->name('financial-statements.destroy');
    Route::post('/portfolio-companies/{company}/financial-statements/{statement}/copy', [App\Http\Controllers\FinancialStatementController::class, 'copy'])->name('financial-statements.copy');


    // ── Financial Planning Models ─────────────────────────────────────────────
    Route::prefix('portfolio-companies/{company}/financial-planning')
        ->name('financial-planning.')
        ->group(function () {
            Route::get('/',                         [App\Http\Controllers\FinancialPlanningController::class, 'index'])->name('index');
            Route::get('/upload',                   [App\Http\Controllers\FinancialPlanningController::class, 'uploadPage'])->name('upload');
            Route::post('/upload',                  [App\Http\Controllers\FinancialPlanningController::class, 'processUpload'])->name('process-upload');
            Route::delete('/{model}',               [App\Http\Controllers\FinancialPlanningController::class, 'destroy'])->name('destroy');
            Route::get('/{model}/download',         [App\Http\Controllers\FinancialPlanningController::class, 'download'])->name('download');
            Route::get('/{model}/assumptions',      [App\Http\Controllers\FinancialPlanningController::class, 'assumptionEditor'])->name('assumptions');
            Route::post('/{model}/assumptions/save',[App\Http\Controllers\FinancialPlanningController::class, 'saveAssumptions'])->name('save-assumptions');
            Route::get('/{model}/live',             [App\Http\Controllers\FinancialPlanningController::class, 'liveEditor'])->name('live');
            Route::post('/{model}/save',            [App\Http\Controllers\FinancialPlanningController::class, 'saveLive'])->name('save-live');
        });

    // ── Cash Forecast ─────────────────────────────────────────────────────────
    Route::get('/portfolio-companies/{company}/cash-forecast',
        [App\Http\Controllers\CashForecastController::class, 'index'])->name('cash-forecast.index');
    Route::get('/portfolio-companies/{company}/financial-statements/{statement}/cash-forecast',
        [App\Http\Controllers\CashForecastController::class, 'index'])->name('cash-forecast.statement');
    Route::post('/portfolio-companies/{company}/cash-forecast/settlement',
        [App\Http\Controllers\CashForecastController::class, 'saveSettlement'])->name('cash-forecast.save-settlement');
    Route::get('/portfolio-companies/{company}/cash-forecast/settlement/{lineItem}',
        [App\Http\Controllers\CashForecastController::class, 'getSettlement'])->name('cash-forecast.get-settlement');
    Route::post('/portfolio-companies/{company}/cash-forecast/entries',
        [App\Http\Controllers\CashForecastController::class, 'storeEntry'])->name('cash-forecast.store-entry');
    Route::put('/portfolio-companies/{company}/cash-forecast/entries/{entry}',
        [App\Http\Controllers\CashForecastController::class, 'updateEntry'])->name('cash-forecast.update-entry');
    Route::delete('/portfolio-companies/{company}/cash-forecast/entries/{entry}',
        [App\Http\Controllers\CashForecastController::class, 'destroyEntry'])->name('cash-forecast.destroy-entry');

   
   
   // ── Budget & Variance ─────────────────────────────────────────────────────
    // IMPORTANT: static word routes (create, {budget}/actuals, {budget}/directors)
    //            MUST come BEFORE wildcard {budget} show/edit routes
    Route::prefix('portfolio-companies/{company}/budgets')
        ->name('budgets.')
        ->group(function () {
            Route::get('/',                    [App\Http\Controllers\BudgetController::class, 'index'])->name('index');
            Route::get('/create',              [App\Http\Controllers\BudgetController::class, 'create'])->name('create');
            Route::post('/',                   [App\Http\Controllers\BudgetController::class, 'store'])->name('store');
            Route::get('/{budget}/edit',       [App\Http\Controllers\BudgetController::class, 'edit'])->name('edit');
            Route::put('/{budget}',            [App\Http\Controllers\BudgetController::class, 'update'])->name('update');
            Route::delete('/{budget}',         [App\Http\Controllers\BudgetController::class, 'destroy'])->name('destroy');
            Route::get('/{budget}',            [App\Http\Controllers\BudgetController::class, 'show'])->name('show');
            Route::get('/{budget}/actuals',    [App\Http\Controllers\BudgetController::class, 'actuals'])->name('actuals');
            Route::post('/{budget}/actuals',   [App\Http\Controllers\BudgetController::class, 'saveActuals'])->name('save-actuals');
            Route::post('/{budget}/actuals/import', [App\Http\Controllers\BudgetController::class, 'importActuals'])->name('import-actuals');

            // ── Sales Director Review Room ────────────────────────────────────
            Route::get('/{budget}/directors',  [App\Http\Controllers\BudgetController::class, 'directors'])->name('directors');
            Route::get('/{budget}/directors/{director}/review',  [App\Http\Controllers\BudgetController::class, 'directorReview'])->name('director-review');
            Route::post('/{budget}/directors/{director}/review', [App\Http\Controllers\BudgetController::class, 'saveDirectorReview'])->name('save-director-review');
        });
   
    
   
        // ── Financial Model Studio ────────────────────────────────────────────────
    Route::prefix('portfolio-companies/{company}/model-studio')
        ->name('model-studio.')
        ->group(function () {
            Route::get('/',           [App\Http\Controllers\ModelStudioController::class, 'index'])->name('index');
            Route::post('/',          [App\Http\Controllers\ModelStudioController::class, 'store'])->name('store');
            Route::get('/{workbook}', [App\Http\Controllers\ModelStudioController::class, 'editor'])->name('editor');
            Route::post('/{workbook}/save',   [App\Http\Controllers\ModelStudioController::class, 'save'])->name('save');
            Route::post('/{workbook}/rename', [App\Http\Controllers\ModelStudioController::class, 'rename'])->name('rename');
            Route::delete('/{workbook}',      [App\Http\Controllers\ModelStudioController::class, 'destroy'])->name('destroy');
        });

    // ── KPI Tracking ──────────────────────────────────────────────────────────
    Route::get('/portfolio-companies/{company}/kpi',                        [App\Http\Controllers\KpiController::class, 'dashboard'])->name('kpi.dashboard');
    Route::get('/portfolio-companies/{company}/kpi/entry',                  [App\Http\Controllers\KpiController::class, 'entryPage'])->name('kpi.entry');
    Route::post('/portfolio-companies/{company}/kpi/entry',                 [App\Http\Controllers\KpiController::class, 'saveEntry'])->name('kpi.save-entry');
    Route::get('/portfolio-companies/{company}/kpi/library',                [App\Http\Controllers\KpiController::class, 'library'])->name('kpi.library');
    Route::post('/portfolio-companies/{company}/kpi/library',               [App\Http\Controllers\KpiController::class, 'storeCustom'])->name('kpi.store-custom');
    Route::patch('/portfolio-companies/{company}/kpi/library/{definition}', [App\Http\Controllers\KpiController::class, 'toggleActive'])->name('kpi.toggle-active');
    Route::delete('portfolio-companies/{company}/kpi/{definition}/delete-custom', [App\Http\Controllers\KpiController::class, 'deleteCustom'])->name('kpi.delete-custom');
    Route::patch('portfolio-companies/{company}/kpi/{definition}/update-custom',[App\Http\Controllers\KpiController::class, 'updateCustom'])->name('kpi.update-custom');

   // ── Data Room ──────────────────────────────────────────────────────────────
    Route::prefix('portfolio-companies/{company}/data-room')
    ->name('data-room.')
    ->group(function () {
        Route::get('/',                        [App\Http\Controllers\DocumentController::class, 'index'])->name('index');
        Route::post('/',                       [App\Http\Controllers\DocumentController::class, 'store'])->name('store');
        Route::post('/sections',               [App\Http\Controllers\DocumentController::class, 'storeSection'])->name('sections.store');
        Route::patch('/sections/{section}',    [App\Http\Controllers\DocumentController::class, 'updateSection'])->name('sections.update');
        Route::delete('/sections/{section}',   [App\Http\Controllers\DocumentController::class, 'destroySection'])->name('sections.destroy');
        Route::post('/sections/{section}/subsections',              [App\Http\Controllers\DocumentController::class, 'storeSubsection'])->name('subsections.store');
        Route::patch('/subsections/{subsection}',                   [App\Http\Controllers\DocumentController::class, 'updateSubsection'])->name('subsections.update');
        Route::delete('/subsections/{subsection}',                  [App\Http\Controllers\DocumentController::class, 'destroySubsection'])->name('subsections.destroy');
        Route::get('/{document}/download',     [App\Http\Controllers\DocumentController::class, 'download'])->name('download');
        Route::get('/{document}/view',         [App\Http\Controllers\DocumentController::class, 'view'])->name('view');
        Route::get('/{document}/sheets',       [App\Http\Controllers\DocumentController::class, 'sheets'])->name('sheets');
        Route::post('/{document}/sheets',      [App\Http\Controllers\DocumentController::class, 'saveSheets'])->name('save-sheets');
        Route::patch('/{document}/rename',     [App\Http\Controllers\DocumentController::class, 'rename'])->name('rename');
        Route::delete('/{document}',           [App\Http\Controllers\DocumentController::class, 'destroy'])->name('destroy');
    });
   
   
    // ── Projects & Task Management ────────────────────────────────────────────────
// Add this block BEFORE the Financial Studies block (before line 638 in web.php)
// Place after the Data Room block.

Route::prefix('portfolio-companies/{company}/projects')
    ->name('projects.')
    ->group(function () {
        // Project CRUD
        Route::get('/',        [App\Http\Controllers\ProjectController::class, 'index'])->name('index');
        Route::post('/',       [App\Http\Controllers\ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [App\Http\Controllers\ProjectController::class, 'show'])->name('show');
        Route::put('/{project}', [App\Http\Controllers\ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [App\Http\Controllers\ProjectController::class, 'destroy'])->name('destroy');

        // Cost rates (static word route — must be BEFORE /{project} wildcard)
        Route::post('/cost-rates', [App\Http\Controllers\ProjectController::class, 'saveCostRates'])->name('cost-rates');

        // Tasks (under a specific project)
        Route::post('/{project}/tasks',                  [App\Http\Controllers\ProjectController::class, 'storeTask'])->name('store-task');
        Route::put('/{project}/tasks/{task}',            [App\Http\Controllers\ProjectController::class, 'updateTask'])->name('update-task');
        Route::delete('/{project}/tasks/{task}',         [App\Http\Controllers\ProjectController::class, 'destroyTask'])->name('destroy-task');
        Route::post('/{project}/tasks/reorder',          [App\Http\Controllers\ProjectController::class, 'reorderTasks'])->name('reorder-tasks');

        // Time logs
        Route::post('/{project}/tasks/{task}/logs',           [App\Http\Controllers\ProjectController::class, 'storeLog'])->name('store-log');
        Route::delete('/{project}/tasks/{task}/logs/{log}',   [App\Http\Controllers\ProjectController::class, 'destroyLog'])->name('destroy-log');

        // Expenses
        Route::post('/{project}/expenses',              [App\Http\Controllers\ProjectController::class, 'storeExpense'])->name('store-expense');
        Route::delete('/{project}/expenses/{expense}',  [App\Http\Controllers\ProjectController::class, 'destroyExpense'])->name('destroy-expense');

        // Refresh endpoint for Vue reactivity
        Route::get('/{project}/refresh',   [App\Http\Controllers\ProjectController::class, 'refresh'])->name('refresh');
    });


// ══════════════════════════════════════════════════════════════════════════════
// ADD THESE ROUTES TO web.php
// ══════════════════════════════════════════════════════════════════════════════


// ── 2. Authenticated Survey Routes ────────────────────────────────────────────
//    Place inside the Route::middleware(['auth'])->group() block

// Surveys — per portfolio company
Route::prefix('portfolio-companies/{company}/surveys')
    ->name('surveys.')
    ->group(function () {
        Route::get('/',                      [App\Http\Controllers\SurveyController::class, 'index'])->name('index');
        Route::get('/create',                [App\Http\Controllers\SurveyController::class, 'create'])->name('create');
        Route::post('/',                     [App\Http\Controllers\SurveyController::class, 'store'])->name('store');
        Route::get('/{survey}/edit',         [App\Http\Controllers\SurveyController::class, 'edit'])->name('edit');
        Route::put('/{survey}',              [App\Http\Controllers\SurveyController::class, 'update'])->name('update');
        Route::delete('/{survey}',           [App\Http\Controllers\SurveyController::class, 'destroy'])->name('destroy');
        Route::post('/{survey}/publish',     [App\Http\Controllers\SurveyController::class, 'publish'])->name('publish');
        Route::post('/{survey}/toggle-status', [App\Http\Controllers\SurveyController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{survey}/copy',        [App\Http\Controllers\SurveyController::class, 'copy'])->name('copy');
        Route::post('/{survey}/save-to-bank', [App\Http\Controllers\SurveyController::class, 'saveToBank'])->name('save-to-bank');
        Route::get('/{survey}/results',      [App\Http\Controllers\SurveyController::class, 'results'])->name('results');
    });

// Question Bank — org-level
Route::prefix('question-bank')
    ->name('question-bank.')
    ->group(function () {
        Route::get('/',                          [App\Http\Controllers\QuestionBankController::class, 'index'])->name('index');
        Route::post('/sections',                 [App\Http\Controllers\QuestionBankController::class, 'storeSection'])->name('store-section');
        Route::put('/sections/{id}',             [App\Http\Controllers\QuestionBankController::class, 'updateSection'])->name('update-section');
        Route::delete('/sections/{id}',          [App\Http\Controllers\QuestionBankController::class, 'destroySection'])->name('destroy-section');
        Route::post('/items',                    [App\Http\Controllers\QuestionBankController::class, 'storeItem'])->name('store-item');
        Route::put('/items/{id}',                [App\Http\Controllers\QuestionBankController::class, 'updateItem'])->name('update-item');
        Route::delete('/items/{id}',             [App\Http\Controllers\QuestionBankController::class, 'destroyItem'])->name('destroy-item');
        Route::put('/items/{id}/move',           [App\Http\Controllers\QuestionBankController::class, 'moveItem'])->name('move-item');
    });
    
   
    
    // ══════════════════════════════════════════════════════════════════════════════
// STATISTICA ROUTES — add inside Route::middleware(['auth'])->group()
// Suggested placement: after InvestaDocs block, before Financial Studies block
// ══════════════════════════════════════════════════════════════════════════════

Route::prefix('organizations/{orgId}/statistica')
    ->name('statistica.')
    ->group(function () {
        Route::get('/',                                              [App\Http\Controllers\StatisticaController::class, 'index'])->name('index');
        Route::post('/',                                             [App\Http\Controllers\StatisticaController::class, 'store'])->name('store');
        Route::get('/compare',                                       [App\Http\Controllers\StatisticaController::class, 'compare'])->name('compare');
        Route::get('/template',                                      [App\Http\Controllers\StatisticaController::class, 'downloadTemplate'])->name('template');
        Route::get('/{seriesId}',                                    [App\Http\Controllers\StatisticaController::class, 'show'])->name('show');
        Route::put('/{seriesId}',                                    [App\Http\Controllers\StatisticaController::class, 'update'])->name('update');
        Route::delete('/{seriesId}',                                 [App\Http\Controllers\StatisticaController::class, 'destroy'])->name('destroy');
        Route::post('/{seriesId}/entries',                           [App\Http\Controllers\StatisticaController::class, 'storeEntry'])->name('store-entry');
        Route::put('/{seriesId}/entries/{entryId}',                  [App\Http\Controllers\StatisticaController::class, 'updateEntry'])->name('update-entry');
        Route::delete('/{seriesId}/entries/{entryId}',               [App\Http\Controllers\StatisticaController::class, 'destroyEntry'])->name('destroy-entry');
        Route::post('/{seriesId}/import',                            [App\Http\Controllers\StatisticaController::class, 'importExcel'])->name('import');
    });
    
    
    
    
    
    
    // ── Financial Studies ─────────────────────────────────────────────────────
    // IMPORTANT: static routes (api/, create) MUST come BEFORE /{study} wildcard
    Route::prefix('portfolio-companies/{company}/financial-studies')
        ->name('financial-studies.')
        ->group(function () {

            // API — must be first (before any /{study} wildcard)
            Route::get('/api/sales-products',
                [FinancialStudyController::class, 'importFromSales'])
                ->name('api.sales-products');

            Route::get('/api/sales-dimension',
                [FinancialStudyController::class, 'importSalesDimension'])
                ->name('api.sales-dimension');

            // API — import expense names from expense_data
            Route::get('/api/expense-names',
                [FinancialStudyController::class, 'importExpenseNames'])
                ->name('api.expense-names');

            // Static word routes — must be before /{study} wildcard
            Route::get('/',        [FinancialStudyController::class, 'index'])->name('index');
            Route::get('/create',  [FinancialStudyController::class, 'create'])->name('create');
            Route::post('/',       [FinancialStudyController::class, 'store'])->name('store');

            // Wildcard /{study} routes
            Route::get('/{study}/edit',    [FinancialStudyController::class, 'edit'])->name('edit');
            Route::put('/{study}',         [FinancialStudyController::class, 'update'])->name('update');
            Route::delete('/{study}',      [FinancialStudyController::class, 'destroy'])->name('destroy');
            Route::get('/{study}/sales',   [FinancialStudyController::class, 'salesStep'])->name('sales');
            Route::post('/{study}/sales',  [FinancialStudyController::class, 'saveSalesStep'])->name('save-sales');
            Route::get('/{study}/cogs',    [FinancialStudyController::class, 'cogsStep'])->name('cogs');
            Route::post('/{study}/cogs',   [FinancialStudyController::class, 'saveCogsStep'])->name('save-cogs');
            Route::get('/{study}/manpower',  [FinancialStudyController::class, 'manpowerStep'])->name('manpower');
            Route::post('/{study}/manpower', [FinancialStudyController::class, 'saveManpowerStep'])->name('save-manpower');
            Route::get('/{study}/expenses',  [FinancialStudyController::class, 'expensesStep'])->name('expenses');
            Route::post('/{study}/expenses', [FinancialStudyController::class, 'saveExpensesStep'])->name('save-expenses');
            Route::post('/{study}/writeup',   [FinancialStudyController::class, 'saveWriteup'])->name('save-writeup');
            Route::get('/{study}/report',     [FinancialStudyController::class, 'reportPage'])->name('report');
        
            Route::get('/{study}/fixed-assets',  [FinancialStudyController::class, 'fixedAssetsStep'])->name('fixed-assets');
            Route::post('/{study}/fixed-assets', [FinancialStudyController::class, 'saveFixedAssetsStep'])->name('save-fixed-assets');
        
           Route::get('/{study}/opening-balance',  [OpeningBalanceController::class, 'show'])->name('opening-balance');
           Route::post('/{study}/opening-balance', [OpeningBalanceController::class, 'store'])->name('save-opening-balance');

            Route::get('/{study}/results', [FinancialStudyController::class, 'resultsStep'])->name('results');
            
        
            });

            // ══════════════════════════════════════════════════════════════════════════════
            // INVESTOR DECISION TOOL ROUTES
            // Add these inside the Route::middleware(['auth'])->group() block in web.php
            // Suggested placement: after the Surveys block, before Financial Studies
            // ══════════════════════════════════════════════════════════════════════════════
            
            
            
            Route::prefix('investor-decision')
            ->name('investor-decision.')
            ->group(function () {
            Route::get('/',                              [App\Http\Controllers\InvestorDecisionController::class, 'index'])->name('index');
            Route::get('/compare',                       [App\Http\Controllers\InvestorDecisionController::class, 'compare'])->name('compare');
            Route::get('/{company}/evaluate',            [App\Http\Controllers\InvestorDecisionController::class, 'evaluate'])->name('evaluate');
            Route::post('/{company}/save',               [App\Http\Controllers\InvestorDecisionController::class, 'saveEvaluation'])->name('save');
    });

    // ══════════════════════════════════════════════════════════════════════════
// USER TASKS ROUTES
// Add inside the Route::middleware(['auth'])->group() block
// Suggested placement: just before the closing }); of the auth group (line 829)
// ══════════════════════════════════════════════════════════════════════════

Route::prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/',                [App\Http\Controllers\UserTaskController::class, 'index'])->name('index');
    Route::post('/',               [App\Http\Controllers\UserTaskController::class, 'store'])->name('store');
    Route::put('/{task}',          [App\Http\Controllers\UserTaskController::class, 'update'])->name('update');
    Route::patch('/{task}/status', [App\Http\Controllers\UserTaskController::class, 'updateStatus'])->name('status');
    Route::delete('/{task}',       [App\Http\Controllers\UserTaskController::class, 'destroy'])->name('destroy');
    Route::get('/badge-count',     [App\Http\Controllers\UserTaskController::class, 'badgeCount'])->name('badge');
});

Route::get('/my-tasks', [App\Http\Controllers\AssignedTaskController::class, 'index'])->name('my-tasks.index');




});


require __DIR__.'/auth.php';

Route::redirect('/','/login');