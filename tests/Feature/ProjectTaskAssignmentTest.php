<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PortfolioCompany;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class ProjectTaskAssignmentTest extends TestCase
{
    private static bool $schemaReady = false;

    private Organization $organization;
    private PortfolioCompany $company;
    private User $admin;
    private User $otherUser;
    private int $projectId;

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

        $this->admin = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Admin User']);
        $this->otherUser = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Other User']);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $this->admin->id,
        ]);

        DB::table('user_company_assignments')->insert([
            'user_id' => $this->otherUser->id,
            'portfolio_company_id' => $this->company->id,
            'role' => 'viewer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->projectId = DB::table('projects')->insertGetId([
            'portfolio_company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'name' => 'Project A',
            'status' => 'not_started',
            'currency' => 'USD',
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

    public function test_project_assignments_include_self_and_feed_my_tasks_page(): void
    {
        $this->actingAs($this->admin)
            ->get("/portfolio-companies/{$this->company->id}/projects/{$this->projectId}")
            ->assertOk()
            ->assertSee('Admin User');

        $this->actingAs($this->admin)
            ->post("/portfolio-companies/{$this->company->id}/projects/{$this->projectId}/tasks", [
                'name' => 'Assigned Task',
                'description' => 'from project',
                'priority' => 'medium',
                'status' => 'not_started',
                'assignee_ids' => [$this->admin->id, $this->otherUser->id],
            ])
            ->assertOk();

        $taskId = DB::table('project_tasks')->where('name', 'Assigned Task')->value('id');

        $this->assertDatabaseHas('project_task_assignees', [
            'project_task_id' => $taskId,
            'user_id' => $this->admin->id,
        ]);
        $this->assertDatabaseMissing('project_task_assignees', [
            'project_task_id' => $taskId,
            'user_id' => $this->otherUser->id,
        ]);

        $this->actingAs($this->admin)
            ->get('/tasks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tasks/Index')
                ->where('counts.total', 0)
            );

        $this->assertDatabaseHas('project_task_assignees', [
            'project_task_id' => $taskId,
            'user_id' => $this->admin->id,
            'seen_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->get('/my-tasks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tasks/MyTasks')
                ->where('tasks.0.name', 'Assigned Task')
                ->where('tasks.0.project_name', 'Project A')
            );

        $this->assertDatabaseMissing('project_task_assignees', [
            'project_task_id' => $taskId,
            'user_id' => $this->admin->id,
            'seen_at' => null,
        ]);
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
        Schema::dropIfExists('user_tasks');
        Schema::dropIfExists('project_task_assignees');
        Schema::dropIfExists('project_task_logs');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('user_cost_rates');
        Schema::dropIfExists('user_company_permissions');
        Schema::dropIfExists('user_company_assignments');
        Schema::dropIfExists('model_has_roles');
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

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
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

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->unsignedBigInteger('created_by');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('phase')->nullable();
            $table->string('status')->default('not_started');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('depends_on_task_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('not_started');
            $table->string('priority')->default('medium');
            $table->integer('order')->default(0);
            $table->integer('estimated_days')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
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
            $table->text('notes')->nullable();
            $table->integer('progress_pct')->nullable();
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

        Schema::create('user_cost_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('daily_rate', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('user_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('portfolio_company_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('not_started');
            $table->date('expected_start_date')->nullable();
            $table->integer('expected_duration_days')->nullable();
            $table->date('expected_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->integer('actual_duration_days')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->text('completion_notes')->nullable();
            $table->timestamps();
        });
    }
}
