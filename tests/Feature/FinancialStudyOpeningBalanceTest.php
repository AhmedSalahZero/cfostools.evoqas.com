<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PortfolioCompany;
use App\Models\StudyOpeningBalance;
use App\Models\User;
use App\Models\UserCompanyAssignment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class FinancialStudyOpeningBalanceTest extends TestCase
{
    private User $user;

    private PortfolioCompany $company;

    private int $studyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDatabase();
        $this->rebuildSchema();

        DB::beginTransaction();

        $organization = Organization::create([
            'name' => 'Test Fund',
            'base_currency' => 'EGP',
        ]);

        $this->company = PortfolioCompany::create([
            'organization_id' => $organization->id,
            'name' => 'Trading Co',
            'sector' => 'Trading',
            'status' => 'on_track',
            'transaction_date' => now()->toDateString(),
            'invested_amount' => 1,
            'invested_currency' => 'EGP',
            'fx_currency' => 'EGP',
            'fx_rate' => 1,
            'equity_stake' => 0.4,
            'entry_valuation' => 100,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        UserCompanyAssignment::create([
            'user_id' => $this->user->id,
            'portfolio_company_id' => $this->company->id,
            'role' => 'manager',
        ]);

        $this->studyId = DB::table('financial_studies')->insertGetId([
            'portfolio_company_id' => $this->company->id,
            'name' => 'BP 2026',
            'study_currency' => 'EGP',
            'study_start_date' => '2026-09-01',
            'duration_years' => 3,
            'study_end_date' => '2029-08-31',
            'business_type' => 'trading',
            'corporate_tax_rate' => 22.5,
            'required_investment_return_pct' => 30,
            'perpetual_growth_rate_pct' => 4,
            'products' => json_encode([['name' => 'Machines', 'nature' => 'trading', 'vat_rate' => 14, 'withhold_tax_rate' => 1]]),
            'cogs_data' => json_encode([[
                'name' => 'Machines',
                'nature' => 'trading',
                'beginning_inventory_units' => 4400,
                'beginning_inventory_value' => 0,
                'unit_purchase_cost' => 11000,
            ]]),
            'projections' => json_encode(['sales' => ['products' => []]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_b01_store_saves_flat_arrays_not_sections(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance",
            $this->payload([
                'current_liabilities' => [[
                    'label' => 'Trade payables',
                    'amount' => 4_000_000,
                    'schedule' => [
                        ['month' => 'Oct 2026', 'amount' => 2_000_000],
                        ['month' => 'Dec 2026', 'amount' => 2_000_000],
                    ],
                ]],
            ])
        );

        $response->assertOk()->assertJson(['success' => true]);

        $json = json_decode(DB::table('financial_studies')->where('id', $this->studyId)->value('opening_balance'), true);
        $this->assertArrayNotHasKey('sections', $json);
        $this->assertSame(4_000_000.0, (float) $json['current_liabilities'][0]['amount']);
        $this->assertSame('Oct 2026', $json['current_liabilities'][0]['schedule'][0]['month']);
        $this->assertSame(4_000_000.0, (float) $json['totals']['current_liabilities']);
    }

    public function test_b02_store_persists_current_assets_and_other_non_current(): void
    {
        $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance",
            $this->payload([
                'current_assets' => [[
                    'label' => 'Trade receivables',
                    'amount' => 6_000_000,
                    'schedule' => [
                        ['month' => 'Oct 2026', 'amount' => 3_000_000],
                        ['month' => 'Nov 2026', 'amount' => 3_000_000],
                    ],
                ]],
                'other_non_current' => [['label' => 'Deposit', 'amount' => 250_000]],
            ])
        )->assertOk();

        $json = json_decode(DB::table('financial_studies')->where('id', $this->studyId)->value('opening_balance'), true);
        $this->assertSame(6_000_000.0, (float) $json['current_assets'][0]['amount']);
        $this->assertSame(250_000.0, (float) $json['other_non_current'][0]['amount']);
    }

    public function test_b03_store_keeps_non_contiguous_settlement_month_labels(): void
    {
        $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance",
            $this->payload([
                'current_liabilities' => [[
                    'label' => 'x',
                    'amount' => 1000,
                    'schedule' => [['month' => 'Dec 2026', 'amount' => 1000]],
                ]],
            ])
        )->assertOk();

        $json = json_decode(DB::table('financial_studies')->where('id', $this->studyId)->value('opening_balance'), true);
        $this->assertCount(1, $json['current_liabilities'][0]['schedule']);
        $this->assertSame('Dec 2026', $json['current_liabilities'][0]['schedule'][0]['month']);
    }

    public function test_b14_store_ignores_posted_inventory_and_rebuilds_from_cogs(): void
    {
        $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance",
            $this->payload([
                'inventory' => [['label' => 'Machines — Trading Inventory', 'type' => 'trading', 'amount' => 1]],
            ])
        )->assertOk();

        $ob = StudyOpeningBalance::where('financial_study_id', $this->studyId)->first();
        $this->assertNotNull($ob);
        $this->assertSame(48_400_000.0, (float) $ob->inventory[0]['amount']);

        $json = json_decode(DB::table('financial_studies')->where('id', $this->studyId)->value('opening_balance'), true);
        $this->assertSame(48_400_000.0, (float) $json['inventory'][0]['amount']);
        $this->assertSame(48_400_000.0, (float) $json['totals']['inventory']);
    }

    public function test_b11_cogs_save_auto_fills_zero_opening_value_and_syncs_inventory(): void
    {
        $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance",
            $this->payload()
        )->assertOk();

        $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/cogs",
            [
                'submit_button' => 'save',
                'cogs_data' => [[
                    'name' => 'Machines',
                    'nature' => 'trading',
                    'beginning_inventory_units' => 100,
                    'beginning_inventory_value' => 0,
                    'unit_purchase_cost' => 50,
                ]],
            ]
        )->assertOk();

        $cogs = json_decode(DB::table('financial_studies')->where('id', $this->studyId)->value('cogs_data'), true);
        $this->assertSame(5000.0, (float) $cogs[0]['beginning_inventory_value']);

        $ob = StudyOpeningBalance::where('financial_study_id', $this->studyId)->first();
        $this->assertSame(5000.0, (float) $ob->inventory[0]['amount']);
    }

    public function test_opening_balance_and_results_pages_load(): void
    {
        $this->actingAs($this->user)
            ->get("/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance")
            ->assertOk();

        $this->actingAs($this->user)
            ->get("/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/results")
            ->assertOk();
    }

    public function test_b19_live_opening_balance_route_is_the_dedicated_controller(): void
    {
        $route = app('router')->getRoutes()->match(
            request()->create("/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance", 'POST')
        );

        $this->assertSame('App\Http\Controllers\OpeningBalanceController@store', $route->getActionName());
    }

    public function test_compute_totals_marks_balanced_sheet_when_cash_and_capital_are_included(): void
    {
        // COGS rebuilds inventory to 4,400 × 11,000 = 48,400,000
        $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance",
            $this->payload([
                'cash_bank' => 20_000_000,
                'paid_up_capital' => 67_400_000,
                'legal_reserve' => 1_000_000,
            ])
        )->assertOk();

        $ob = StudyOpeningBalance::where('financial_study_id', $this->studyId)->first();
        $this->assertTrue($ob->is_balanced);
        $this->assertEqualsWithDelta(68_400_000.0, (float) $ob->total_assets, 0.01);
        $this->assertEqualsWithDelta(68_400_000.0, (float) $ob->total_equity, 0.01);

        $json = json_decode(DB::table('financial_studies')->where('id', $this->studyId)->value('opening_balance'), true);
        $this->assertTrue($json['is_balanced']);
        $this->assertEqualsWithDelta(68_400_000.0, (float) $json['totals']['total_assets'], 0.01);
        $this->assertEqualsWithDelta(68_400_000.0, (float) $json['totals']['equity'], 0.01);
    }

    public function test_compute_totals_unbalanced_payable_is_off_by_four_million(): void
    {
        $this->actingAs($this->user)->postJson(
            "/portfolio-companies/{$this->company->id}/financial-studies/{$this->studyId}/opening-balance",
            $this->payload([
                'cash_bank' => 20_000_000,
                'paid_up_capital' => 67_400_000,
                'legal_reserve' => 1_000_000,
                'current_liabilities' => [['label' => 'AP', 'amount' => 4_000_000]],
            ])
        )->assertOk();

        $ob = StudyOpeningBalance::where('financial_study_id', $this->studyId)->first();
        $diff = (float) $ob->total_assets - ((float) $ob->total_liabilities + (float) $ob->total_equity);
        $this->assertFalse($ob->is_balanced);
        $this->assertEqualsWithDelta(-4_000_000.0, $diff, 0.01);
        $this->assertEqualsWithDelta(68_400_000.0, (float) $ob->total_assets, 0.01);
        $this->assertEqualsWithDelta(4_000_000.0, (float) $ob->total_liabilities, 0.01);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'source' => 'manual',
            'cash_bank' => 20_000_000,
            'paid_up_capital' => 66_520_000,
            'legal_reserve' => 1_000_000,
            'retained_earnings' => 0,
            'fixed_assets' => [],
            'inventory' => [],
            'current_assets' => [],
            'other_non_current' => [],
            'long_term_liabilities' => [],
            'current_liabilities' => [],
            'equity' => [],
        ], $overrides);
    }

    private function configureDatabase(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            return;
        }

        $name = 'cfostools_testing';
        $mysql = config('database.connections.mysql');
        $pdo = new PDO("mysql:host={$mysql['host']};port={$mysql['port']}", $mysql['username'], $mysql['password']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $name,
        ]);

        DB::purge();
        DB::setDefaultConnection('mysql');
    }

    private function rebuildSchema(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        Schema::dropIfExists('study_opening_balances');
        Schema::dropIfExists('financial_studies');
        Schema::dropIfExists('user_company_assignments');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('portfolio_companies');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');

        if (config('database.default') === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('base_currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('portfolio_companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('type')->default('investment');
            $table->string('name');
            $table->string('lead_source', 100)->nullable();
            $table->string('sector', 100);
            $table->string('status')->default('on_track');
            $table->date('transaction_date');
            $table->decimal('invested_amount', 15, 2);
            $table->char('invested_currency', 3);
            $table->char('fx_currency', 3)->default('USD');
            $table->decimal('fx_rate', 12, 6);
            $table->decimal('equity_stake', 5, 4);
            $table->decimal('entry_valuation', 15, 2);
            $table->timestamps();
        });

        Schema::create('user_company_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('role')->default('viewer');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('financial_studies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('name');
            $table->string('study_currency')->default('EGP');
            $table->date('study_start_date');
            $table->integer('duration_years')->default(5);
            $table->date('study_end_date')->nullable();
            $table->date('operation_start_date')->nullable();
            $table->string('business_type')->default('trading');
            $table->string('business_sector')->nullable();
            $table->decimal('corporate_tax_rate', 8, 4)->default(22.50);
            $table->decimal('required_investment_return_pct', 8, 4)->default(30.00);
            $table->decimal('perpetual_growth_rate_pct', 8, 4)->default(4.00);
            $table->json('general_assumptions')->nullable();
            $table->json('products')->nullable();
            $table->json('projections')->nullable();
            $table->json('cogs_data')->nullable();
            $table->json('manpower_data')->nullable();
            $table->json('expenses_data')->nullable();
            $table->json('fixed_assets_data')->nullable();
            $table->json('opening_balance')->nullable();
            $table->longText('writeups')->nullable();
            $table->timestamps();
        });

        Schema::create('study_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('financial_study_id')->unique();
            $table->date('as_of_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_balanced')->default(false);
            $table->decimal('cash_bank', 18, 2)->default(0);
            $table->decimal('paid_up_capital', 18, 2)->default(0);
            $table->decimal('legal_reserve', 18, 2)->default(0);
            $table->decimal('retained_earnings', 18, 2)->default(0);
            $table->json('fixed_assets')->nullable();
            $table->decimal('total_gross_fa', 18, 2)->default(0);
            $table->decimal('total_accum_dep', 18, 2)->default(0);
            $table->decimal('total_net_fa', 18, 2)->default(0);
            $table->json('inventory')->nullable();
            $table->decimal('total_inventory', 18, 2)->default(0);
            $table->json('current_assets')->nullable();
            $table->decimal('total_current_assets', 18, 2)->default(0);
            $table->json('other_non_current')->nullable();
            $table->decimal('total_other_non_current', 18, 2)->default(0);
            $table->json('long_term_liabilities')->nullable();
            $table->decimal('total_long_term_liabilities', 18, 2)->default(0);
            $table->json('current_liabilities')->nullable();
            $table->decimal('total_current_liabilities', 18, 2)->default(0);
            $table->json('equity')->nullable();
            $table->decimal('total_equity', 18, 2)->default(0);
            $table->decimal('total_assets', 18, 2)->default(0);
            $table->decimal('total_liabilities', 18, 2)->default(0);
            $table->timestamps();
        });
    }
}
