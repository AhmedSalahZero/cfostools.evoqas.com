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

/**
 * "Save & continue later" on the public survey form: a respondent can stop
 * midway, keep a resume link, come back, and finish.
 */
class SurveyDraftResumeTest extends TestCase
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

        // postJson() drops cookies unless credentials are enabled; a real
        // same-origin browser request always sends them.
        $this->withCredentials();

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

    // ── Saving ───────────────────────────────────────────────────────────────

    public function test_saving_a_draft_stores_progress_and_sets_the_resume_cookie(): void
    {
        [$survey, $token] = $this->activeSurvey();
        $rating = $this->question($survey->id, 'rating');

        $response = $this->postJson(route('survey.public.draft', $token), [
            'respondent' => ['name' => 'Dana', 'title' => 'CFO'],
            'answers' => [$rating->id => '4'],
        ])->assertOk()->assertJsonStructure(['saved_at', 'expires_in']);

        // The token travels in a cookie, never in the URL — the survey link stays the same
        $this->assertArrayNotHasKey('resume_url', $response->json());
        $this->assertArrayNotHasKey('draft_token', $response->json());

        $draftToken = $this->draftCookieValue($response, (int) $survey->id);
        $this->assertSame(64, strlen($draftToken));

        $draft = DB::table('survey_drafts')->where('token', $draftToken)->first();
        $this->assertNotNull($draft);
        $this->assertSame((int) $survey->id, (int) $draft->survey_id);
        $this->assertSame('Dana', json_decode($draft->respondent, true)['name']);
        $this->assertSame('4', json_decode($draft->answers, true)[$rating->id]);

        // A draft is not a response — it must stay out of the results
        $this->assertSame(0, DB::table('survey_responses')->where('survey_id', $survey->id)->count());
        $this->assertSame(0, (int) DB::table('surveys')->where('id', $survey->id)->value('response_count'));
    }

    public function test_saving_again_updates_the_same_draft_instead_of_creating_another(): void
    {
        [$survey, $token] = $this->activeSurvey();
        $rating = $this->question($survey->id, 'rating');
        $text = $this->question($survey->id, 'short_text');

        $first = $this->postJson(route('survey.public.draft', $token), [
            'answers' => [$rating->id => '2'],
        ])->assertOk();

        $firstToken = $this->draftCookieValue($first, (int) $survey->id);

        $second = $this->withCookie($this->cookieName((int) $survey->id), $firstToken)
            ->postJson(route('survey.public.draft', $token), [
                'answers' => [$rating->id => '5', $text->id => 'Much better now'],
            ])->assertOk();

        $this->assertSame($firstToken, $this->draftCookieValue($second, (int) $survey->id));
        $this->assertSame(1, DB::table('survey_drafts')->where('survey_id', $survey->id)->count());

        $answers = json_decode(DB::table('survey_drafts')->where('token', $firstToken)->value('answers'), true);
        $this->assertSame('5', $answers[$rating->id]);
        $this->assertSame('Much better now', $answers[$text->id]);
    }

    public function test_a_draft_cannot_be_saved_for_a_closed_survey(): void
    {
        [$survey, $token] = $this->activeSurvey();
        DB::table('surveys')->where('id', $survey->id)->update(['status' => 'closed']);

        $this->postJson(route('survey.public.draft', $token), ['answers' => []])
            ->assertNotFound();

        $this->assertSame(0, DB::table('survey_drafts')->count());
    }

    // ── Resuming ─────────────────────────────────────────────────────────────

    public function test_reopening_the_same_survey_link_restores_the_draft(): void
    {
        [$survey, $token] = $this->activeSurvey();
        $rating = $this->question($survey->id, 'rating');

        $saved = $this->postJson(route('survey.public.draft', $token), [
            'respondent' => ['name' => 'Dana', 'title' => 'CFO', 'age' => '41'],
            'answers' => [$rating->id => '4'],
        ]);

        $draftToken = $this->draftCookieValue($saved, (int) $survey->id);

        // Exactly the same URL the respondent was given in the first place
        $this->withCookie($this->cookieName((int) $survey->id), $draftToken)
            ->get(route('survey.public', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Public')
                ->where('draft.token', $draftToken)
                ->where('draft.respondent.name', 'Dana')
                ->where('draft.respondent.title', 'CFO')
                ->where('draft.respondent.age', '41')
                ->where('draft.answers.'.$rating->id, '4')
            );
    }

    public function test_the_form_loads_empty_for_a_browser_with_no_draft(): void
    {
        [, $token] = $this->activeSurvey();

        $this->get(route('survey.public', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Public')
                ->where('draft', null)
            );
    }

    public function test_a_stale_draft_cookie_is_ignored_rather_than_failing(): void
    {
        [$survey, $token] = $this->activeSurvey();

        $this->withCookie($this->cookieName((int) $survey->id), str_repeat('x', 64))
            ->get(route('survey.public', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('draft', null));
    }

    public function test_a_draft_from_another_survey_cannot_be_resumed(): void
    {
        [$surveyA, $tokenA] = $this->activeSurvey('Survey A', 'draft-token-a');
        [$surveyB, $tokenB] = $this->activeSurvey('Survey B', 'draft-token-b');

        $rating = $this->question($surveyA->id, 'rating');

        $saved = $this->postJson(route('survey.public.draft', $tokenA), [
            'answers' => [$rating->id => '3'],
        ]);
        $draftToken = $this->draftCookieValue($saved, (int) $surveyA->id);

        // Survey A's draft token presented on survey B must not leak its answers
        $this->withCookie($this->cookieName((int) $surveyB->id), $draftToken)
            ->get(route('survey.public', $tokenB))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('draft', null));
    }

    // ── Finishing ────────────────────────────────────────────────────────────

    public function test_submitting_records_the_response_and_clears_the_draft(): void
    {
        [$survey, $token] = $this->activeSurvey();
        $rating = $this->question($survey->id, 'rating');

        $saved = $this->postJson(route('survey.public.draft', $token), [
            'answers' => [$rating->id => '4'],
        ]);
        $draftToken = $this->draftCookieValue($saved, (int) $survey->id);

        $this->withCookie($this->cookieName((int) $survey->id), $draftToken)
            ->post(route('survey.public.submit', $token), [
                'respondent_name' => 'Dana',
                'answers' => [$rating->id => '5'],
            ])->assertOk();

        $this->assertSame(0, DB::table('survey_drafts')->where('token', $draftToken)->count());
        $this->assertSame(1, DB::table('survey_responses')->where('survey_id', $survey->id)->count());
        $this->assertSame(1, (int) DB::table('surveys')->where('id', $survey->id)->value('response_count'));

        // The submitted value wins over whatever the draft held
        $this->assertSame('5', DB::table('survey_answers')->where('survey_question_id', $rating->id)->value('answer_text'));
    }

    public function test_submitting_without_ever_saving_a_draft_still_works(): void
    {
        [$survey, $token] = $this->activeSurvey();
        $rating = $this->question($survey->id, 'rating');

        $this->post(route('survey.public.submit', $token), [
            'answers' => [$rating->id => '3'],
        ])->assertOk();

        $this->assertSame(1, DB::table('survey_responses')->where('survey_id', $survey->id)->count());
    }

    public function test_every_answer_type_survives_save_resume_and_submit(): void
    {
        [$survey, $token] = $this->activeSurvey();

        $rating = $this->question($survey->id, 'rating');
        $text = $this->question($survey->id, 'short_text');
        $mcq = $this->question($survey->id, 'mcq');
        $multi = $this->question($survey->id, 'mcq_multi');
        $matrix = $this->question($survey->id, 'matrix');

        $mcqOption = DB::table('survey_question_options')->where('survey_question_id', $mcq->id)->first();
        $multiOptions = DB::table('survey_question_options')->where('survey_question_id', $multi->id)->orderBy('sort_order')->get();
        $matrixOption = DB::table('survey_question_options')->where('survey_question_id', $matrix->id)->first();
        $matrixRow = DB::table('survey_matrix_rows')->where('survey_question_id', $matrix->id)->first();

        $answers = [
            $rating->id => '4',
            $text->id => 'Detailed feedback',
            $mcq->id => $mcqOption->id,
            $multi->id => [$multiOptions[0]->id, $multiOptions[1]->id],
            $matrix->id => [$matrixRow->id => $matrixOption->id],
        ];

        $saved = $this->postJson(route('survey.public.draft', $token), [
            'answers' => $answers,
        ])->assertOk();
        $draftToken = $this->draftCookieValue($saved, (int) $survey->id);
        $cookieName = $this->cookieName((int) $survey->id);

        // Resume: every shape comes back intact, including the nested ones
        $this->withCookie($cookieName, $draftToken)
            ->get(route('survey.public', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('draft.answers.'.$rating->id, '4')
                ->where('draft.answers.'.$text->id, 'Detailed feedback')
                ->where('draft.answers.'.$mcq->id, (int) $mcqOption->id)
                ->where('draft.answers.'.$multi->id, [(int) $multiOptions[0]->id, (int) $multiOptions[1]->id])
                ->where('draft.answers.'.$matrix->id.'.'.$matrixRow->id, (int) $matrixOption->id)
            );

        // Finish from the resumed state
        $this->withCookie($cookieName, $draftToken)
            ->post(route('survey.public.submit', $token), ['answers' => $answers])
            ->assertOk();

        $responseId = DB::table('survey_responses')->where('survey_id', $survey->id)->value('id');

        $this->assertSame('4', DB::table('survey_answers')->where('survey_response_id', $responseId)->where('survey_question_id', $rating->id)->value('answer_text'));
        $this->assertSame('Detailed feedback', DB::table('survey_answers')->where('survey_response_id', $responseId)->where('survey_question_id', $text->id)->value('answer_text'));
        $this->assertSame((int) $mcqOption->id, (int) DB::table('survey_answers')->where('survey_response_id', $responseId)->where('survey_question_id', $mcq->id)->value('answer_option_id'));
        $this->assertSame(2, DB::table('survey_answers')->where('survey_response_id', $responseId)->where('survey_question_id', $multi->id)->count());
        $this->assertSame((int) $matrixOption->id, (int) DB::table('survey_answers')->where('survey_response_id', $responseId)->where('survey_question_id', $matrix->id)->where('matrix_row_id', $matrixRow->id)->value('answer_option_id'));

        $this->assertSame(0, DB::table('survey_drafts')->where('token', $draftToken)->count());
    }

    public function test_deleting_a_survey_removes_its_drafts(): void
    {
        [$survey, $token] = $this->activeSurvey();
        $rating = $this->question($survey->id, 'rating');

        $saved = $this->postJson(route('survey.public.draft', $token), [
            'answers' => [$rating->id => '2'],
        ]);
        $draftToken = $this->draftCookieValue($saved, (int) $survey->id);

        $this->assertSame(1, DB::table('survey_drafts')->where('token', $draftToken)->count());

        $this->actingAs($this->user)
            ->delete(route('surveys.destroy', [$this->company->id, $survey->id]))
            ->assertRedirect();

        $this->assertSame(0, DB::table('survey_drafts')->where('token', $draftToken)->count());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array{0: object, 1: string} the survey row and its public link token */
    private function activeSurvey(string $title = 'Board Pulse', string $token = 'draft-test-token'): array
    {
        $this->actingAs($this->user)
            ->post(route('surveys.store', $this->company->id), [
                'title' => $title,
                'questions' => [
                    ['question_text' => 'Rate us', 'question_type' => 'rating', 'rating_max' => 5],
                    ['question_text' => 'Anything else?', 'question_type' => 'short_text'],
                    ['question_text' => 'Pick one', 'question_type' => 'mcq', 'options' => ['Alpha', 'Beta']],
                    ['question_text' => 'Pick many', 'question_type' => 'mcq_multi', 'options' => ['One', 'Two', 'Three']],
                    [
                        'question_text' => 'Rate each area',
                        'question_type' => 'matrix',
                        'options' => ['Poor', 'Good'],
                        'rows' => ['Finance', 'Operations'],
                    ],
                ],
            ])
            ->assertRedirect(route('surveys.index', $this->company->id));

        $survey = DB::table('surveys')->where('title', $title)->orderByDesc('id')->first();

        DB::table('surveys')->where('id', $survey->id)->update([
            'status' => 'active',
            'link_token' => $token,
            'updated_at' => now(),
        ]);

        return [$survey, $token];
    }

    private function cookieName(int $surveyId): string
    {
        return 'survey_draft_'.$surveyId;
    }

    /** The decrypted draft token the response set on this browser. */
    private function draftCookieValue($response, int $surveyId): string
    {
        $cookie = $response->getCookie($this->cookieName($surveyId));

        $this->assertNotNull($cookie, 'Expected the save-draft response to set a draft cookie.');

        return $cookie->getValue();
    }

    private function question(int $surveyId, string $type): object
    {
        return DB::table('survey_questions')
            ->where('survey_id', $surveyId)
            ->where('question_type', $type)
            ->orderBy('sort_order')
            ->first();
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
        Schema::dropIfExists('survey_drafts');
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

        Schema::create('survey_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('token', 64)->unique();
            $table->json('respondent')->nullable();
            $table->json('answers')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('survey_id')->references('id')->on('surveys')->cascadeOnDelete();
        });
    }
}
