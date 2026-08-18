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

class UserManagementVisibilityTest extends TestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDatabase();

        if (!self::$schemaReady) {
            $this->rebuildSchema();
            self::$schemaReady = true;
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_super_admin_created_user_is_visible_to_company_admin_of_that_organization(): void
    {
        $orgA = Organization::create(['name' => 'Org A', 'base_currency' => 'USD']);
        $orgB = Organization::create(['name' => 'Org B', 'base_currency' => 'USD']);

        $companyA = $this->makeCompany($orgA->id, 'Alpha Co');

        $superAdmin = User::factory()->create(['organization_id' => null]);
        $adminA = User::factory()->create(['organization_id' => $orgA->id]);
        $adminB = User::factory()->create(['organization_id' => $orgB->id]);

        $this->attachRole($superAdmin, 'super-admin');
        $this->attachRole($adminA, 'admin');
        $this->attachRole($adminB, 'admin');

        $payload = [
            'name' => 'Scoped User',
            'email' => 'scoped@example.com',
            'password' => 'password123',
            'assigned_companies' => [[
                'id' => $companyA->id,
                'role' => 'viewer',
                'permissions' => ['view_company'],
            ]],
        ];

        $this->actingAs($superAdmin)
            ->post(route('users.store'), $payload)
            ->assertRedirect(route('users.index'));

        $newUser = User::where('email', 'scoped@example.com')->firstOrFail();

        $this->assertSame($orgA->id, $newUser->organization_id);

        $this->actingAs($adminA)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Scoped User')
            ->assertSee('scoped@example.com');

        $this->actingAs($adminB)
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('scoped@example.com');
    }

    private function attachRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => $roleName,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    private function makeCompany(int $organizationId, string $name): PortfolioCompany
    {
        return PortfolioCompany::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'sector' => 'Technology',
            'status' => 'on_track',
            'transaction_date' => now()->toDateString(),
            'invested_amount' => 1,
            'invested_currency' => 'USD',
            'fx_currency' => 'USD',
            'fx_rate' => 1,
            'equity_stake' => 0.4,
            'entry_valuation' => 100,
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
    }
}
