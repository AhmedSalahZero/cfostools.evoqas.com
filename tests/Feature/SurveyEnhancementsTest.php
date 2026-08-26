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

class SurveyEnhancementsTest extends TestCase
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

    public function test_store_persists_sections_and_matrix_items(): void
    {
        $this->actingAs($this->user)
            ->post(route('surveys.store', $this->company->id), [
                'title' => 'Enhanced Survey',
                'items' => [
                    [
                        'kind' => 'section',
                        'client_id' => 'sec-fin',
                        'title' => 'Financial Questions',
                    ],
                    [
                        'kind' => 'question',
                        'section_ref' => 'sec-fin',
                        'question_text' => 'Revenue growth?',
                        'question_type' => 'mcq',
                        'is_required' => false,
                        'options' => ['Low', 'High'],
                    ],
                    [
                        'kind' => 'question',
                        'section_ref' => null,
                        'question_text' => 'Leadership matrix',
                        'question_type' => 'matrix',
                        'is_required' => true,
                        'options' => ['Agree', 'Disagree'],
                        'rows' => ['We communicate well', 'Goals are clear'],
                    ],
                ],
            ])
            ->assertRedirect(route('surveys.index', $this->company->id));

        $survey = DB::table('surveys')->orderByDesc('id')->first();

        $this->assertSame(1, DB::table('survey_sections')->where('survey_id', $survey->id)->count());

        $section = DB::table('survey_sections')->where('survey_id', $survey->id)->first();
        $mcq = DB::table('survey_questions')
            ->where('survey_id', $survey->id)
            ->where('question_type', 'mcq')
            ->first();
        $matrix = DB::table('survey_questions')
            ->where('survey_id', $survey->id)
            ->where('question_type', 'matrix')
            ->first();

        $this->assertSame((int) $section->id, (int) $mcq->survey_section_id);
        $this->assertNull($matrix->survey_section_id);
        $this->assertSame(2, DB::table('survey_matrix_rows')->where('survey_question_id', $matrix->id)->count());
        $this->assertSame(2, DB::table('survey_question_options')->where('survey_question_id', $matrix->id)->count());
    }

    public function test_public_submit_stores_matrix_answers(): void
    {
        $survey = $this->storeMatrixSurvey();
        $token = $this->activateSurvey((int) $survey->id);

        $matrix = DB::table('survey_questions')
            ->where('survey_id', $survey->id)
            ->where('question_type', 'matrix')
            ->first();
        $rows = DB::table('survey_matrix_rows')
            ->where('survey_question_id', $matrix->id)
            ->orderBy('sort_order')
            ->get();
        $options = DB::table('survey_question_options')
            ->where('survey_question_id', $matrix->id)
            ->orderBy('sort_order')
            ->get();

        $this->post(route('survey.public.submit', $token), [
            'answers' => [
                $matrix->id => [
                    $rows[0]->id => $options[0]->id,
                    $rows[1]->id => $options[1]->id,
                ],
            ],
        ])->assertOk();

        $saved = DB::table('survey_answers')
            ->where('survey_question_id', $matrix->id)
            ->orderBy('matrix_row_id')
            ->get();

        $this->assertCount(2, $saved);
        $this->assertSame((int) $rows[0]->id, (int) $saved[0]->matrix_row_id);
        $this->assertSame((int) $options[0]->id, (int) $saved[0]->answer_option_id);
        $this->assertSame((int) $rows[1]->id, (int) $saved[1]->matrix_row_id);
        $this->assertSame((int) $options[1]->id, (int) $saved[1]->answer_option_id);
    }

    public function test_export_returns_excel_download(): void
    {
        $survey = $this->storeMatrixSurvey();
        $token = $this->activateSurvey((int) $survey->id);

        $matrix = DB::table('survey_questions')
            ->where('survey_id', $survey->id)
            ->where('question_type', 'matrix')
            ->first();
        $rows = DB::table('survey_matrix_rows')
            ->where('survey_question_id', $matrix->id)
            ->orderBy('sort_order')
            ->get();
        $options = DB::table('survey_question_options')
            ->where('survey_question_id', $matrix->id)
            ->orderBy('sort_order')
            ->get();

        $this->post(route('survey.public.submit', $token), [
            'respondent_name' => 'Sara',
            'answers' => [
                $matrix->id => [
                    $rows[0]->id => $options[0]->id,
                    $rows[1]->id => $options[1]->id,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('surveys.export', [$this->company->id, $survey->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_results_include_matrix_analytics(): void
    {
        $survey = $this->storeMatrixSurvey();
        $token = $this->activateSurvey((int) $survey->id);

        $matrix = DB::table('survey_questions')
            ->where('survey_id', $survey->id)
            ->where('question_type', 'matrix')
            ->first();
        $rows = DB::table('survey_matrix_rows')
            ->where('survey_question_id', $matrix->id)
            ->orderBy('sort_order')
            ->get();
        $options = DB::table('survey_question_options')
            ->where('survey_question_id', $matrix->id)
            ->orderBy('sort_order')
            ->get();

        $this->post(route('survey.public.submit', $token), [
            'answers' => [
                $matrix->id => [
                    $rows[0]->id => $options[0]->id,
                    $rows[1]->id => $options[0]->id,
                ],
            ],
        ]);

        $this->actingAs($this->user)
            ->get(route('surveys.results', [$this->company->id, $survey->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Results')
                ->has('questions', 1)
                ->where('questions.0.question_type', 'matrix')
                ->has('questions.0.analytics', 2)
            );
    }

    private function storeMatrixSurvey(): object
    {
        $this->actingAs($this->user)
            ->post(route('surveys.store', $this->company->id), [
                'title' => 'Matrix Survey',
                'items' => [
                    [
                        'kind' => 'question',
                        'question_text' => 'Team assessment',
                        'question_type' => 'matrix',
                        'is_required' => false,
                        'options' => ['Strongly Agree', 'Strongly Disagree'],
                        'rows' => ['Row A', 'Row B'],
                    ],
                ],
            ])
            ->assertRedirect(route('surveys.index', $this->company->id));

        return DB::table('surveys')->orderByDesc('id')->first();
    }

    private function activateSurvey(int $surveyId): string
    {
        $token = 'enhanced-token-'.$surveyId;

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
