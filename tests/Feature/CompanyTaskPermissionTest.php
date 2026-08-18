<?php

namespace Tests\Feature;

use App\Http\Controllers\UserManagementController;
use App\Models\Organization;
use App\Models\PortfolioCompany;
use App\Models\User;
use App\Models\UserCompanyAssignment;
use App\Models\UserCompanyPermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class CompanyTaskPermissionTest extends TestCase
{
    private static bool $schemaReady = false;

    private Organization $organization;
    private PortfolioCompany $company;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDatabase();

        if (!self::$schemaReady) {
            $this->rebuildSchema();
            self::$schemaReady = true;
        }

        DB::beginTransaction();

        $this->organization = Organization::create(['name' => 'Org', 'base_currency' => 'USD']);
        $this->company = PortfolioCompany::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Co',
            'sector' => 'Tech',
            'status' => 'on_track',
            'transaction_date' => now()->toDateString(),
            'invested_amount' => 1,
            'invested_currency' => 'USD',
            'fx_currency' => 'USD',
            'fx_rate' => 1,
            'equity_stake' => 0.4,
            'entry_valuation' => 100,
        ]);

        $this->viewer = User::factory()->create(['organization_id' => $this->organization->id]);

        UserCompanyAssignment::create([
            'user_id' => $this->viewer->id,
            'portfolio_company_id' => $this->company->id,
            'role' => 'viewer',
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_permissions_constant_contains_new_modules(): void
    {
        $this->assertArrayHasKey('contracts', UserManagementController::PERMISSIONS);
        $this->assertArrayHasKey('surveys', UserManagementController::PERMISSIONS);
        $this->assertArrayHasKey('statistica', UserManagementController::PERMISSIONS);
        $this->assertArrayHasKey('projects', UserManagementController::PERMISSIONS);
    }

    public function test_contracts_surveys_and_projects_require_explicit_permission_for_viewer(): void
    {
        $this->actingAs($this->viewer)->get("/portfolio-companies/{$this->company->id}/contracts")->assertForbidden();
        $this->actingAs($this->viewer)->get("/portfolio-companies/{$this->company->id}/surveys")->assertForbidden();
        $this->actingAs($this->viewer)->get("/portfolio-companies/{$this->company->id}/projects")->assertForbidden();

        foreach (['contracts', 'surveys', 'projects'] as $permission) {
            UserCompanyPermission::create([
                'user_id' => $this->viewer->id,
                'portfolio_company_id' => $this->company->id,
                'permission' => $permission,
            ]);
        }

        $this->actingAs($this->viewer)->get("/portfolio-companies/{$this->company->id}/contracts")->assertOk();
        $this->actingAs($this->viewer)->get("/portfolio-companies/{$this->company->id}/surveys")->assertOk();
        $this->actingAs($this->viewer)->get("/portfolio-companies/{$this->company->id}/projects")->assertOk();
    }

    public function test_statistica_requires_permission_for_non_admin_users(): void
    {
        $url = "/organizations/{$this->organization->id}/statistica";

        $this->actingAs($this->viewer)->get($url)->assertForbidden();

        UserCompanyPermission::create([
            'user_id' => $this->viewer->id,
            'portfolio_company_id' => $this->company->id,
            'permission' => 'statistica',
        ]);

        $this->actingAs($this->viewer)->get($url)->assertOk();
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

        Schema::dropIfExists('kpi_trackings');
        Schema::dropIfExists('statistica_entries');
        Schema::dropIfExists('statistica_series');
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('project_task_logs');
        Schema::dropIfExists('project_task_assignees');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('customer_contracts');
        Schema::dropIfExists('user_company_permissions');
        Schema::dropIfExists('user_company_assignments');
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
            $table->string('lead_source')->nullable();
            $table->string('sector');
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

        Schema::create('user_company_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('permission');
            $table->timestamps();
        });

        Schema::create('customer_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('link_token', 64)->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_template')->default(false);
            $table->integer('response_count')->default(0);
            $table->timestamps();
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('question_text');
            $table->string('question_type')->default('short_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('survey_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_question_id');
            $table->string('option_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->unsignedBigInteger('created_by');
            $table->string('name');
            $table->string('status')->default('not_started');
            $table->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('depends_on_task_id')->nullable();
            $table->string('name');
            $table->string('status')->default('not_started');
            $table->string('priority')->default('medium');
            $table->integer('order')->default(0);
            $table->integer('progress_pct')->default(0);
            $table->timestamps();
        });

        Schema::create('project_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_task_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_task_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_task_id');
            $table->unsignedBigInteger('user_id');
            $table->date('log_date')->nullable();
            $table->decimal('hours', 6, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('created_by');
            $table->string('category')->default('other');
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('expense_date')->nullable();
            $table->timestamps();
        });

        Schema::create('statistica_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('category')->default('General');
            $table->string('unit')->nullable();
            $table->string('frequency')->nullable();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('statistica_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('series_id');
            $table->date('entry_date');
            $table->decimal('value', 15, 4);
            $table->timestamps();
        });
    }
}
