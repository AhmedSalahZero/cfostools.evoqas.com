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

class SurveyTemplateStartTest extends TestCase
{
    private static bool $schemaReady = false;

    private User $user;

    private Organization $organization;

    private PortfolioCompany $company;

    private PortfolioCompany $sisterCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDatabase();

        if (! self::$schemaReady) {
            $this->rebuildSchema();
            self::$schemaReady = true;
        }

        DB::beginTransaction();

        $this->organization = Organization::create([
            'name' => 'Test Fund',
            'base_currency' => 'USD',
        ]);

        $this->company = $this->makeCompany($this->organization->id, 'Acme Portfolio Co');
        $this->sisterCompany = $this->makeCompany($this->organization->id, 'Sister Co');

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
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

    public function test_create_shows_org_templates_and_hides_non_templates_and_other_orgs(): void
    {
        $orgTemplate = $this->insertSurvey($this->sisterCompany, [
            'title' => 'Org Template',
            'prepared_by' => 'IR',
            'is_template' => true,
        ], ['How satisfied are you?']);

        $this->insertSurvey($this->company, [
            'title' => 'Not A Template',
            'is_template' => false,
        ], ['Skip me']);

        $otherOrg = Organization::create(['name' => 'Other Fund', 'base_currency' => 'USD']);
        $otherCompany = $this->makeCompany($otherOrg->id, 'Outsider Co');
        $this->insertSurvey($otherCompany, [
            'title' => 'Foreign Template',
            'is_template' => true,
        ], ['Secret']);

        $this->actingAs($this->user)
            ->get(route('surveys.create', $this->company->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Start')
                ->has('templates', 1)
                ->where('templates.0.id', $orgTemplate)
                ->where('templates.0.title', 'Org Template')
                ->where('templates.0.prepared_by', 'IR')
            );
    }

    public function test_from_template_hydrates_builder_without_survey_id(): void
    {
        $templateId = $this->insertSurvey($this->sisterCompany, [
            'title' => 'NPS Template',
            'introduction' => 'Please rate us',
            'prepared_by' => 'Research',
            'default_respondent_company' => 'Acme Holdings',
            'is_template' => true,
        ], ['Would you recommend us?']);

        $this->actingAs($this->user)
            ->get(route('surveys.create', [
                'company' => $this->company->id,
                'from_template' => $templateId,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Create')
                ->where('survey.id', null)
                ->where('survey.title', 'NPS Template')
                ->where('survey.introduction', 'Please rate us')
                ->where('survey.default_respondent_company', 'Acme Holdings')
                ->where('survey.is_template', false)
                ->has('survey.questions', 1)
                ->where('survey.questions.0.question_text', 'Would you recommend us?')
            );
    }

    public function test_from_template_from_another_organization_is_not_found(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Fund', 'base_currency' => 'USD']);
        $otherCompany = $this->makeCompany($otherOrg->id, 'Outsider Co');
        $foreignId = $this->insertSurvey($otherCompany, [
            'title' => 'Foreign Template',
            'is_template' => true,
        ], ['Secret']);

        $this->actingAs($this->user)
            ->get(route('surveys.create', [
                'company' => $this->company->id,
                'from_template' => $foreignId,
            ]))
            ->assertNotFound();
    }

    public function test_saving_prefilled_payload_creates_new_survey_and_leaves_template(): void
    {
        $templateId = $this->insertSurvey($this->sisterCompany, [
            'title' => 'NPS Template',
            'is_template' => true,
        ], ['Would you recommend us?']);

        $this->actingAs($this->user)
            ->post(route('surveys.store', $this->company->id), [
                'title' => 'NPS for Acme',
                'introduction' => 'Please rate us',
                'is_template' => false,
                'questions' => [
                    [
                        'question_text' => 'Would you recommend us?',
                        'question_type' => 'rating',
                        'is_required' => true,
                        'rating_max' => 10,
                        'options' => [],
                    ],
                ],
            ])
            ->assertRedirect(route('surveys.index', $this->company->id));

        $created = DB::table('surveys')
            ->where('portfolio_company_id', $this->company->id)
            ->where('title', 'NPS for Acme')
            ->first();

        $this->assertNotNull($created);
        $this->assertFalse((bool) $created->is_template);
        $this->assertSame(1, (int) DB::table('survey_questions')->where('survey_id', $created->id)->count());

        $template = DB::table('surveys')->where('id', $templateId)->first();
        $this->assertSame('NPS Template', $template->title);
        $this->assertSame($this->sisterCompany->id, (int) $template->portfolio_company_id);
        $this->assertTrue((bool) $template->is_template);
        $this->assertSame(1, (int) DB::table('survey_questions')->where('survey_id', $templateId)->count());
    }

    private function makeCompany(int $organizationId, string $name): PortfolioCompany
    {
        return PortfolioCompany::create([
            'organization_id' => $organizationId,
            'name' => $name,
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
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<string>  $questionTexts
     */
    private function insertSurvey(PortfolioCompany $company, array $attrs, array $questionTexts): int
    {
        $surveyId = DB::table('surveys')->insertGetId([
            'portfolio_company_id' => $company->id,
            'organization_id' => $company->organization_id,
            'created_by' => $this->user->id,
            'title' => $attrs['title'],
            'introduction' => $attrs['introduction'] ?? null,
            'prepared_by' => $attrs['prepared_by'] ?? null,
            'default_respondent_name' => $attrs['default_respondent_name'] ?? null,
            'default_respondent_title' => $attrs['default_respondent_title'] ?? null,
            'default_respondent_company' => $attrs['default_respondent_company'] ?? null,
            'show_respondent_age' => $attrs['show_respondent_age'] ?? false,
            'show_respondent_gender' => $attrs['show_respondent_gender'] ?? false,
            'is_template' => $attrs['is_template'] ?? false,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($questionTexts as $i => $text) {
            DB::table('survey_questions')->insert([
                'survey_id' => $surveyId,
                'question_text' => $text,
                'question_type' => 'short_text',
                'sort_order' => $i,
                'is_required' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $surveyId;
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
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
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
