<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PortfolioCompany;
use App\Models\User;
use App\Models\UserCompanyAssignment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class QuestionBankColorAndSaveTest extends TestCase
{
    private static bool $schemaReady = false;

    private User $user;

    private PortfolioCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDatabase();

        if (! self::$schemaReady) {
            $this->rebuildSchema();
            self::$schemaReady = true;
        }

        DB::beginTransaction();

        $organization = Organization::create([
            'name' => 'Test Fund',
            'base_currency' => 'USD',
        ]);

        $this->company = PortfolioCompany::create([
            'organization_id' => $organization->id,
            'name' => 'Acme Portfolio Co',
            'sector' => 'Technology',
            'status' => 'on_track',
            'transaction_date' => now()->toDateString(),
            'invested_amount' => 1000000,
            'invested_currency' => 'USD',
            'fx_currency' => 'USD',
            'fx_rate' => 1,
            'equity_stake' => 0.4,
            'entry_valuation' => 2500000,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        UserCompanyAssignment::create([
            'user_id' => $this->user->id,
            'portfolio_company_id' => $this->company->id,
            'role' => 'manager',
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_store_section_persists_hex_color(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('question-bank.store-section'), [
                'name' => 'Finance',
                'color' => '#c9a227',
            ])
            ->assertOk()
            ->assertJson([
                'name' => 'Finance',
                'color' => '#c9a227',
            ]);

        $this->assertDatabaseHas('question_bank_sections', [
            'name' => 'Finance',
            'color' => '#c9a227',
            'organization_id' => $this->user->organization_id,
        ]);
    }

    public function test_store_item_survives_index_reload(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('question-bank.store-item'), [
                'question_text' => 'What is your NPS?',
                'question_type' => 'rating',
                'is_required' => true,
                'rating_max' => 10,
            ])
            ->assertOk();

        $this->assertDatabaseHas('question_bank_items', [
            'question_text' => 'What is your NPS?',
            'organization_id' => $this->user->organization_id,
        ]);

        $this->actingAs($this->user)
            ->get(route('question-bank.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('QuestionBank/Index')
                ->has('items', 1)
                ->where('items.0.question_text', 'What is your NPS?')
            );
    }

    public function test_store_item_uses_assigned_company_org_when_user_org_id_is_null(): void
    {
        $this->user->forceFill(['organization_id' => null])->save();

        $this->actingAs($this->user->fresh())
            ->postJson(route('question-bank.store-item'), [
                'question_text' => 'Orphan user question',
                'question_type' => 'short_text',
            ])
            ->assertOk();

        $this->assertDatabaseHas('question_bank_items', [
            'question_text' => 'Orphan user question',
            'organization_id' => $this->company->organization_id,
        ]);

        $this->actingAs($this->user->fresh())
            ->get(route('question-bank.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('QuestionBank/Index')
                ->has('items', 1)
                ->where('items.0.question_text', 'Orphan user question')
            );
    }

    public function test_store_item_uses_first_org_for_super_admin_without_org(): void
    {
        $super = User::factory()->create(['organization_id' => null]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'super-admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $super->id,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);

        $this->actingAs($super)
            ->postJson(route('question-bank.store-item'), [
                'question_text' => 'Super admin question',
                'question_type' => 'yes_no',
                'options' => ['Yes', 'No'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('question_bank_items', [
            'question_text' => 'Super admin question',
            'organization_id' => $this->company->organization_id,
        ]);
    }

    public function test_survey_create_includes_bank_items_from_user_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Fund',
            'base_currency' => 'USD',
        ]);

        $otherCompany = PortfolioCompany::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Co',
            'sector' => 'Healthcare',
            'status' => 'on_track',
            'transaction_date' => now()->toDateString(),
            'invested_amount' => 1000000,
            'invested_currency' => 'USD',
            'fx_currency' => 'USD',
            'fx_rate' => 1,
            'equity_stake' => 0.4,
            'entry_valuation' => 2500000,
        ]);

        DB::table('question_bank_items')->insert([
            'organization_id' => $this->user->organization_id,
            'question_text' => 'User org bank question',
            'question_type' => 'short_text',
            'is_required' => false,
            'rating_max' => 5,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('question_bank_items')->insert([
            'organization_id' => $otherOrg->id,
            'question_text' => 'Company org bank question',
            'question_type' => 'yes_no',
            'is_required' => false,
            'rating_max' => 5,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('surveys.create', $otherCompany->id).'?blank=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Create')
                ->has('bankItems', 2)
                ->where('bankItems', fn ($items) => collect($items)->pluck('question_text')->sort()->values()->all() === [
                    'Company org bank question',
                    'User org bank question',
                ])
            );
    }

    public function test_store_survey_copies_flagged_questions_into_the_bank(): void
    {
        $sectionId = DB::table('question_bank_sections')->insertGetId([
            'organization_id' => $this->user->organization_id,
            'name' => 'Finance',
            'color' => '#c9a227',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->post(route('surveys.store', $this->company->id), [
                'title' => 'Bank Cycle Survey',
                'questions' => [
                    [
                        'question_text' => 'Which KPIs?',
                        'question_type' => 'mcq',
                        'is_required' => true,
                        'options' => ['Revenue', 'EBITDA'],
                        'save_to_bank' => true,
                        'bank_section_id' => $sectionId,
                    ],
                    [
                        'question_text' => 'Any comments?',
                        'question_type' => 'short_text',
                        'is_required' => false,
                        'options' => [],
                        'save_to_bank' => false,
                    ],
                ],
            ])
            ->assertRedirect(route('surveys.index', $this->company->id));

        $this->assertSame(1, (int) DB::table('question_bank_items')->count());

        $item = DB::table('question_bank_items')->first();
        $this->assertSame('Which KPIs?', $item->question_text);
        $this->assertSame('mcq', $item->question_type);
        $this->assertSame($sectionId, (int) $item->question_bank_section_id);
        $this->assertTrue((bool) $item->is_required);
        $this->assertSame(
            ['Revenue', 'EBITDA'],
            DB::table('question_bank_item_options')
                ->where('question_bank_item_id', $item->id)
                ->orderBy('sort_order')
                ->pluck('option_text')
                ->all()
        );
    }

    protected function configureDatabase(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            return;
        }

        $name = 'cfostools_testing';
        $mysql = config('database.connections.mysql');

        $pdo = new PDO(
            "mysql:host={$mysql['host']};port={$mysql['port']}",
            $mysql['username'],
            $mysql['password']
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $name,
        ]);

        DB::purge();
        DB::setDefaultConnection('mysql');
    }

    protected function rebuildSchema(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        Schema::dropIfExists('question_bank_item_options');
        Schema::dropIfExists('question_bank_items');
        Schema::dropIfExists('question_bank_sections');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
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
            $table->string('legal_structure', 100)->nullable();
            $table->string('logo')->nullable();
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
            $table->decimal('ebitda_multiplier', 6, 2)->nullable();
            $table->decimal('entry_valuation', 15, 2);
            $table->decimal('current_valuation', 15, 2)->nullable();
            $table->decimal('moic', 5, 2)->nullable();
            $table->decimal('irr', 5, 2)->nullable();
            $table->date('last_financial_update')->nullable();
            $table->text('notes')->nullable();
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

        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('default_respondent_name')->nullable();
            $table->string('default_respondent_title')->nullable();
            $table->string('default_respondent_company')->nullable();
            $table->boolean('show_respondent_age')->default(false);
            $table->boolean('show_respondent_gender')->default(false);
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
            $table->string('question_type');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->string('placeholder')->nullable();
            $table->integer('rating_max')->default(5);
            $table->timestamps();
        });

        Schema::create('survey_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_question_id');
            $table->string('option_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('question_bank_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('color', 20)->default('blue');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('question_bank_section_id')->nullable();
            $table->string('question_text');
            $table->string('question_type');
            $table->boolean('is_required')->default(false);
            $table->integer('rating_max')->default(5);
            $table->string('placeholder')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        Schema::create('question_bank_item_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_bank_item_id');
            $table->string('option_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
}
