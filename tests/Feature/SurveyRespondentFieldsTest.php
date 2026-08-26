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

class SurveyRespondentFieldsTest extends TestCase
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

    public function test_store_with_defaults_exposes_readonly_fields_and_hides_age_gender_on_public_form(): void
    {
        $survey = $this->storeSurvey([
            'title' => 'Investor Pulse',
            'default_respondent_name' => 'Ahmed Ali',
            'default_respondent_title' => 'CFO',
            'default_respondent_company' => 'Acme Holdings',
            'show_respondent_age' => false,
            'show_respondent_gender' => false,
        ]);

        $this->assertSame('Ahmed Ali', $survey->default_respondent_name);
        $this->assertSame('CFO', $survey->default_respondent_title);
        $this->assertSame('Acme Holdings', $survey->default_respondent_company);
        $this->assertFalse((bool) $survey->show_respondent_age);
        $this->assertFalse((bool) $survey->show_respondent_gender);

        $token = $this->activateSurvey($survey->id);

        $this->get(route('survey.public', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Public')
                ->where('survey.default_respondent_name', 'Ahmed Ali')
                ->where('survey.default_respondent_title', 'CFO')
                ->where('survey.default_respondent_company', 'Acme Holdings')
                ->where('survey.show_respondent_age', false)
                ->where('survey.show_respondent_gender', false)
            );
    }

    public function test_store_without_company_default_shows_optional_age_and_gender_flags(): void
    {
        $survey = $this->storeSurvey([
            'title' => 'Open Feedback',
            'default_respondent_name' => '',
            'default_respondent_title' => '',
            'default_respondent_company' => '',
            'show_respondent_age' => true,
            'show_respondent_gender' => true,
        ]);

        $this->assertNull($survey->default_respondent_name);
        $this->assertNull($survey->default_respondent_title);
        $this->assertNull($survey->default_respondent_company);
        $this->assertTrue((bool) $survey->show_respondent_age);
        $this->assertTrue((bool) $survey->show_respondent_gender);

        $token = $this->activateSurvey($survey->id);

        $this->get(route('survey.public', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Public')
                ->where('survey.default_respondent_name', null)
                ->where('survey.default_respondent_company', null)
                ->where('survey.show_respondent_age', true)
                ->where('survey.show_respondent_gender', true)
            );
    }

    public function test_public_submit_uses_survey_defaults_and_ignores_spoofed_name(): void
    {
        $survey = $this->storeSurvey([
            'title' => 'Locked Name',
            'default_respondent_name' => 'Sara Nabil',
            'default_respondent_title' => 'Controller',
            'default_respondent_company' => 'Nabil Group',
            'show_respondent_age' => false,
            'show_respondent_gender' => false,
        ]);

        $token = $this->activateSurvey($survey->id);

        $this->post(route('survey.public.submit', $token), [
            'respondent_name' => 'Spoofed Name',
            'respondent_title' => 'Spoofed Title',
            'respondent_company' => 'Spoofed Co',
            'respondent_age' => 41,
            'respondent_gender' => 'male',
            'answers' => [],
        ])->assertOk();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'respondent_name' => 'Sara Nabil',
            'respondent_title' => 'Controller',
            'respondent_company' => 'Nabil Group',
            'respondent_age' => null,
            'respondent_gender' => null,
        ]);
    }

    public function test_hidden_age_and_gender_posted_values_are_not_stored(): void
    {
        $survey = $this->storeSurvey([
            'title' => 'No Demographics',
            'show_respondent_age' => false,
            'show_respondent_gender' => false,
        ]);

        $token = $this->activateSurvey($survey->id);

        $this->post(route('survey.public.submit', $token), [
            'respondent_name' => 'Omar',
            'respondent_title' => 'Analyst',
            'respondent_company' => 'Should Hide',
            'respondent_age' => 29,
            'respondent_gender' => 'female',
            'answers' => [],
        ])->assertOk();

        $row = DB::table('survey_responses')->where('survey_id', $survey->id)->first();

        $this->assertSame('Omar', $row->respondent_name);
        $this->assertSame('Analyst', $row->respondent_title);
        $this->assertNull($row->respondent_company);
        $this->assertNull($row->respondent_age);
        $this->assertNull($row->respondent_gender);
    }

    public function test_guest_can_open_active_public_survey(): void
    {
        $survey = $this->storeSurvey(['title' => 'Public Pulse']);
        $token = $this->activateSurvey($survey->id);

        $this->get(route('survey.public', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Surveys/Public'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function storeSurvey(array $overrides = []): object
    {
        $payload = array_merge([
            'title' => 'Sample Survey',
            'introduction' => 'Hello',
            'prepared_by' => 'IR team',
            'default_respondent_name' => null,
            'default_respondent_title' => null,
            'default_respondent_company' => null,
            'show_respondent_age' => false,
            'show_respondent_gender' => false,
            'is_template' => false,
            'questions' => [],
        ], $overrides);

        $this->actingAs($this->user)
            ->post(route('surveys.store', $this->company->id), $payload)
            ->assertRedirect(route('surveys.index', $this->company->id));

        return DB::table('surveys')->orderByDesc('id')->first();
    }

    private function activateSurvey(int $surveyId): string
    {
        $token = 'survey-token-'.$surveyId;

        DB::table('surveys')->where('id', $surveyId)->update([
            'status' => 'active',
            'link_token' => $token,
            'updated_at' => now(),
        ]);

        return $token;
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

        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_matrix_rows');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('survey_sections');
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

        Schema::create('survey_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('survey_section_id')->nullable();
            $table->string('question_text');
            $table->string('question_type');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->string('placeholder')->nullable();
            $table->integer('rating_max')->default(5);
            $table->timestamps();
        });

        Schema::create('survey_matrix_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_question_id');
            $table->string('row_text');
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

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('respondent_name')->nullable();
            $table->string('respondent_title')->nullable();
            $table->string('respondent_company')->nullable();
            $table->string('respondent_gender')->nullable();
            $table->unsignedTinyInteger('respondent_age')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_response_id');
            $table->unsignedBigInteger('survey_question_id');
            $table->unsignedBigInteger('matrix_row_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->unsignedBigInteger('answer_option_id')->nullable();
            $table->timestamps();
        });
    }
}
