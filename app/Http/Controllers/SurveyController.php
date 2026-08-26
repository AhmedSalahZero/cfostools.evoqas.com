<?php

namespace App\Http\Controllers;

use App\Exports\SurveyResponsesExport;
use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SurveyController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // INDEX — List all surveys for a company
    // ─────────────────────────────────────────────────────────────────────────
    public function index(PortfolioCompany $company)
    {
        $this->authorizeCompany($company, 'surveys');

        $surveys = DB::table('surveys as s')
            ->leftJoin('users as u', 'u.id', '=', 's.created_by')
            ->where('s.portfolio_company_id', $company->id)
            ->select(
                's.id', 's.title', 's.status', 's.link_token', 's.is_template',
                's.response_count', 's.prepared_by', 's.created_at',
                DB::raw('(SELECT COUNT(*) FROM survey_questions WHERE survey_id = s.id) as question_count'),
                DB::raw("CONCAT(u.name) as creator_name")
            )
            ->orderByDesc('s.created_at')
            ->get();

        $this->rememberQuestionBankOrg($company);

        return Inertia::render('Surveys/Index', [
            'company' => $this->surveyCompanyPayload($company),
            'surveys' => $surveys,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE — Chooser, blank builder, or hydrate from org template
    // ─────────────────────────────────────────────────────────────────────────
    public function create(Request $request, PortfolioCompany $company)
    {
        $this->authorizeCompany($company, 'surveys');

        if (!$request->boolean('blank') && !$request->filled('from_template')) {
            $this->rememberQuestionBankOrg($company);

            return Inertia::render('Surveys/Create', [
                'company'      => $this->surveyCompanyPayload($company),
                'templates'    => $this->organizationTemplates($company),
                'start'        => true,
                'bankSections' => [],
                'bankItems'    => [],
                'survey'       => null,
            ]);
        }

        $survey = null;
        if ($request->filled('from_template')) {
            $survey = $this->hydrateFromTemplate($company, (int) $request->input('from_template'));
        }

        $this->rememberQuestionBankOrg($company);

        [$bankSections, $bankItems] = $this->questionBankFor($company);

        return Inertia::render('Surveys/Create', [
            'company'      => $this->surveyCompanyPayload($company),
            'bankSections' => $bankSections,
            'bankItems'    => $bankItems,
            'survey'       => $survey,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE — Save new survey
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request, PortfolioCompany $company)
    {
        $this->authorizeCompany($company, 'surveys');

        $request->validate([
            'title'       => 'required|string|max:255',
            'questions'   => 'nullable|array',
            ...$this->respondentFieldRules(),
        ]);

        $surveyId = DB::table('surveys')->insertGetId([
            'portfolio_company_id' => $company->id,
            'organization_id'      => $company->organization_id,
            'created_by'           => auth()->id(),
            'title'                => $request->title,
            'introduction'         => $request->introduction,
            'prepared_by'          => $request->prepared_by,
            ...$this->respondentFieldValues($request),
            'status'               => 'draft',
            'is_template'          => $request->boolean('is_template'),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $items = $this->normalizeSurveyItems($request);
        $this->syncSurveyItems($surveyId, $items);
        $this->copyFlaggedQuestionsToBank($company, $this->questionsFromItems($items));

        return redirect()->route('surveys.index', $company->id)
            ->with('success', 'Survey created successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT — Show builder with existing data
    // ─────────────────────────────────────────────────────────────────────────
    public function edit(PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        $survey = DB::table('surveys')->where('id', $surveyId)
            ->where('portfolio_company_id', $company->id)->firstOrFail();

        $survey->items = $this->loadSurveyItems($surveyId);
        $this->castRespondentFlags($survey);

        $this->rememberQuestionBankOrg($company);

        [$bankSections, $bankItems] = $this->questionBankFor($company);

        return Inertia::render('Surveys/Create', [
            'company'      => $this->surveyCompanyPayload($company),
            'survey'       => $survey,
            'bankSections' => $bankSections,
            'bankItems'    => $bankItems,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        $request->validate([
            'title' => 'required|string|max:255',
            ...$this->respondentFieldRules(),
        ]);

        DB::table('surveys')->where('id', $surveyId)->update([
            'title'        => $request->title,
            'introduction' => $request->introduction,
            'prepared_by'  => $request->prepared_by,
            ...$this->respondentFieldValues($request),
            'is_template'  => $request->boolean('is_template'),
            'updated_at'   => now(),
        ]);

        $items = $this->normalizeSurveyItems($request);
        $this->syncSurveyItems($surveyId, $items);
        $this->copyFlaggedQuestionsToBank($company, $this->questionsFromItems($items));

        return redirect()->route('surveys.index', $company->id)
            ->with('success', 'Survey updated.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        DB::table('surveys')->where('id', $surveyId)
            ->where('portfolio_company_id', $company->id)->delete();

        return back()->with('success', 'Survey deleted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLISH — Generate link token and set active
    // ─────────────────────────────────────────────────────────────────────────
    public function publish(PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        $token = Str::random(32);
        DB::table('surveys')->where('id', $surveyId)->update([
            'status'     => 'active',
            'link_token' => $token,
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'token' => $token]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TOGGLE STATUS — active ↔ closed
    // ─────────────────────────────────────────────────────────────────────────
    public function toggleStatus(PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        $survey = DB::table('surveys')->where('id', $surveyId)->first();
        $newStatus = $survey->status === 'active' ? 'closed' : 'active';
        DB::table('surveys')->where('id', $surveyId)->update([
            'status' => $newStatus, 'updated_at' => now()
        ]);
        return response()->json(['success' => true, 'status' => $newStatus]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COPY — Duplicate survey with new title + new link
    // ─────────────────────────────────────────────────────────────────────────
    public function copy(Request $request, PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $original = DB::table('surveys')->where('id', $surveyId)->firstOrFail();

        $newId = DB::table('surveys')->insertGetId([
            'portfolio_company_id' => $company->id,
            'organization_id'      => $company->organization_id,
            'created_by'           => auth()->id(),
            'title'                => trim($request->title),
            'introduction'         => $original->introduction,
            'prepared_by'          => $original->prepared_by,
            'default_respondent_name'    => $original->default_respondent_name ?? null,
            'default_respondent_title'   => $original->default_respondent_title ?? null,
            'default_respondent_company' => $original->default_respondent_company ?? null,
            'show_respondent_age'        => (bool) ($original->show_respondent_age ?? false),
            'show_respondent_gender'     => (bool) ($original->show_respondent_gender ?? false),
            'status'               => 'draft',
            'is_template'          => false,
            'link_token'           => null,
            'response_count'       => 0,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $questions = DB::table('survey_questions')
            ->where('survey_id', $surveyId)->orderBy('sort_order')->get();

        $sectionMap = [];
        $sections = DB::table('survey_sections')
            ->where('survey_id', $surveyId)->orderBy('sort_order')->get();
        foreach ($sections as $section) {
            $sectionMap[$section->id] = DB::table('survey_sections')->insertGetId([
                'survey_id'   => $newId,
                'title'       => $section->title,
                'sort_order'  => $section->sort_order,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        foreach ($questions as $q) {
            $newQId = DB::table('survey_questions')->insertGetId([
                'survey_id'         => $newId,
                'survey_section_id' => ($q->survey_section_id ?? null) ? ($sectionMap[$q->survey_section_id] ?? null) : null,
                'question_text'     => $q->question_text,
                'question_type'     => $q->question_type,
                'sort_order'        => $q->sort_order,
                'is_required'       => $q->is_required,
                'placeholder'       => $q->placeholder,
                'rating_max'        => $q->rating_max,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $opts = DB::table('survey_question_options')
                ->where('survey_question_id', $q->id)->orderBy('sort_order')->get();
            foreach ($opts as $opt) {
                DB::table('survey_question_options')->insert([
                    'survey_question_id' => $newQId,
                    'option_text'        => $opt->option_text,
                    'sort_order'         => $opt->sort_order,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            if ($q->question_type === 'matrix') {
                $rows = DB::table('survey_matrix_rows')
                    ->where('survey_question_id', $q->id)->orderBy('sort_order')->get();
                foreach ($rows as $row) {
                    DB::table('survey_matrix_rows')->insert([
                        'survey_question_id' => $newQId,
                        'row_text'           => $row->row_text,
                        'sort_order'         => $row->sort_order,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }
        }

        return back()->with('success', 'Survey copied successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE TO BANK — Save selected questions to question bank
    // ─────────────────────────────────────────────────────────────────────────
    public function saveToBank(Request $request, PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        $request->validate([
            'question_ids'  => 'required|array',
            'section_id'    => 'nullable|integer',
        ]);

        $orgId = $company->organization_id;

        foreach ($request->question_ids as $qId) {
            $q = DB::table('survey_questions')->where('id', $qId)
                ->where('survey_id', $surveyId)->first();
            if (!$q) continue;

            $bankItemId = DB::table('question_bank_items')->insertGetId([
                'organization_id'          => $orgId,
                'question_bank_section_id' => $request->section_id,
                'question_text'            => $q->question_text,
                'question_type'            => $q->question_type,
                'is_required'              => $q->is_required,
                'rating_max'               => $q->rating_max,
                'placeholder'              => $q->placeholder,
                'usage_count'              => 0,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            $opts = DB::table('survey_question_options')
                ->where('survey_question_id', $qId)->orderBy('sort_order')->get();
            foreach ($opts as $opt) {
                DB::table('question_bank_item_options')->insert([
                    'question_bank_item_id' => $bankItemId,
                    'option_text'           => $opt->option_text,
                    'sort_order'            => $opt->sort_order,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC SURVEY — Show survey form (no auth required)
    // ─────────────────────────────────────────────────────────────────────────
    public function publicShow($token)
    {
        $survey = DB::table('surveys')->where('link_token', $token)->first();

        if (!$survey) abort(404);
        if ($survey->status === 'closed') {
            return Inertia::render('Surveys/Closed');
        }
        if ($survey->status === 'draft') abort(404);

        $questions = $this->loadQuestionsForDisplay($survey->id);
        $sections = DB::table('survey_sections')
            ->where('survey_id', $survey->id)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        $this->castRespondentFlags($survey);

        return Inertia::render('Surveys/Public', [
            'survey'    => $survey,
            'questions' => $questions,
            'sections'  => $sections,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC SUBMIT — Record a response
    // ─────────────────────────────────────────────────────────────────────────
    public function publicSubmit(Request $request, $token)
    {
        $survey = DB::table('surveys')->where('link_token', $token)
            ->where('status', 'active')->firstOrFail();

        $responseId = DB::table('survey_responses')->insertGetId([
            'survey_id'          => $survey->id,
            'respondent_name'    => $this->filledDefault($survey->default_respondent_name) ?? $request->respondent_name,
            'respondent_title'   => $this->filledDefault($survey->default_respondent_title) ?? $request->respondent_title,
            'respondent_company' => $this->filledDefault($survey->default_respondent_company),
            'respondent_gender'  => !empty($survey->show_respondent_gender) ? $request->respondent_gender : null,
            'respondent_age'     => !empty($survey->show_respondent_age) ? ($request->respondent_age ?: null) : null,
            'ip_address'         => $request->ip(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        foreach ($request->answers ?? [] as $qId => $answer) {
            $q = DB::table('survey_questions')->find($qId);
            if (!$q) continue;

            if ($q->question_type === 'matrix') {
                $this->storeMatrixAnswers((int) $responseId, (int) $qId, $answer);
                continue;
            }

            if ($q->question_type === 'mcq_multi') {
                $this->storeMultiSelectAnswers((int) $responseId, (int) $qId, $answer);
                continue;
            }

            if (in_array($q->question_type, ['mcq', 'dropdown'])) {
                DB::table('survey_answers')->insert([
                    'survey_response_id'  => $responseId,
                    'survey_question_id'  => $qId,
                    'answer_text'         => null,
                    'answer_option_id'    => $answer,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            } else {
                DB::table('survey_answers')->insert([
                    'survey_response_id'  => $responseId,
                    'survey_question_id'  => $qId,
                    'answer_text'         => $answer,
                    'answer_option_id'    => null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }

        // Update cached response count
        DB::table('surveys')->where('id', $survey->id)
            ->increment('response_count');

        return Inertia::render('Surveys/ThankYou');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESULTS — Analytics dashboard (auth required)
    // ─────────────────────────────────────────────────────────────────────────
    public function results(PortfolioCompany $company, $surveyId)
    {
        $this->authorizeCompany($company, 'surveys');

        $survey = DB::table('surveys')->where('id', $surveyId)
            ->where('portfolio_company_id', $company->id)->firstOrFail();

        $questions = $this->loadQuestionsForDisplay($surveyId, withAnalytics: true);
        $sections = DB::table('survey_sections')
            ->where('survey_id', $surveyId)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        // Respondent demographics
        $responses = DB::table('survey_responses')->where('survey_id', $surveyId)->get();
        $demographics = [
            'total'   => $responses->count(),
            'gender'  => $responses->groupBy('respondent_gender')->map->count(),
            'age_groups' => $this->buildAgeGroups($responses),
            'companies' => $responses->whereNotNull('respondent_company')
                ->groupBy('respondent_company')->map->count()->sortDesc()->take(10),
            'recent' => $responses->sortByDesc('created_at')->take(10)->values()->map(fn($r) => [
                'name'    => $r->respondent_name ?? 'Anonymous',
                'company' => $r->respondent_company,
                'title'   => $r->respondent_title,
                'date'    => $r->created_at,
            ]),
        ];

        return Inertia::render('Surveys/Results', [
            'company'      => ['id' => $company->id, 'name' => $company->name],
            'survey'       => $survey,
            'questions'    => $questions,
            'sections'     => $sections,
            'demographics' => $demographics,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORT — Download all responses as Excel
    // ─────────────────────────────────────────────────────────────────────────
    public function exportResponses(PortfolioCompany $company, $surveyId): BinaryFileResponse
    {
        $this->authorizeCompany($company, 'surveys');

        $survey = DB::table('surveys')->where('id', $surveyId)
            ->where('portfolio_company_id', $company->id)->firstOrFail();

        $questions = $this->loadQuestionsForDisplay((int) $surveyId);
        $sections = DB::table('survey_sections')
            ->where('survey_id', $surveyId)
            ->get()
            ->keyBy('id');

        [$headings, $columnKeys] = $this->buildExportColumns($questions, $sections);

        $responses = DB::table('survey_responses')
            ->where('survey_id', $surveyId)
            ->orderBy('created_at')
            ->get();

        $rows = $responses->map(function ($response) use ($questions, $sections, $columnKeys) {
            $answersByQuestion = DB::table('survey_answers as a')
                ->leftJoin('survey_question_options as o', 'o.id', '=', 'a.answer_option_id')
                ->where('a.survey_response_id', $response->id)
                ->select('a.*', 'o.option_text')
                ->get()
                ->groupBy('survey_question_id');

            $row = [
                $response->created_at,
                $response->respondent_name,
                $response->respondent_title,
                $response->respondent_company,
                $response->respondent_age,
                $response->respondent_gender,
            ];

            foreach ($columnKeys as $key) {
                if ($key['type'] === 'question') {
                    $row[] = $this->formatExportAnswer(
                        $key['question'],
                        $answersByQuestion->get($key['question']->id, collect())
                    );
                } elseif ($key['type'] === 'matrix_row') {
                    $row[] = $this->formatMatrixRowAnswer(
                        $key['question'],
                        $key['row'],
                        $answersByQuestion->get($key['question']->id, collect())
                    );
                }
            }

            return $row;
        })->all();

        $filename = Str::slug($survey->title).'-responses-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(
            new SurveyResponsesExport($survey->title, $headings, $rows),
            $filename
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function organizationTemplates(PortfolioCompany $company)
    {
        return DB::table('surveys as s')
            ->where('s.organization_id', $company->organization_id)
            ->where('s.is_template', true)
            ->select(
                's.id',
                's.title',
                's.prepared_by',
                DB::raw('(SELECT COUNT(*) FROM survey_questions WHERE survey_id = s.id) as question_count')
            )
            ->orderBy('s.title')
            ->get();
    }

    private function hydrateFromTemplate(PortfolioCompany $company, int $templateId): object
    {
        $survey = DB::table('surveys')
            ->where('id', $templateId)
            ->where('organization_id', $company->organization_id)
            ->where('is_template', true)
            ->first();

        if (!$survey) {
            abort(404);
        }

        $survey->items = $this->loadSurveyItems($survey->id);
        $this->castRespondentFlags($survey);
        $survey->is_template = false;
        $survey->id = null;

        return $survey;
    }

    private function surveyCompanyPayload(PortfolioCompany $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'organization_id' => $company->organization_id,
        ];
    }

    private function rememberQuestionBankOrg(PortfolioCompany $company): void
    {
        session(['question_bank_organization_id' => (int) $company->organization_id]);
    }

    private function questionBankFor(PortfolioCompany $company): array
    {
        $orgIds = collect([
            (int) $company->organization_id,
            (int) auth()->user()?->organization_id,
        ])->filter()->unique()->values()->all();

        if ($orgIds === []) {
            return [collect(), collect()];
        }

        $bankSections = DB::table('question_bank_sections')
            ->whereIn('organization_id', $orgIds)
            ->orderBy('sort_order')->get();

        $bankItems = DB::table('question_bank_items as qi')
            ->whereIn('qi.organization_id', $orgIds)
            ->select('qi.*')
            ->orderBy('qi.question_bank_section_id')->orderByDesc('qi.usage_count')
            ->get()
            ->map(function ($item) {
                $item->options = DB::table('question_bank_item_options')
                    ->where('question_bank_item_id', $item->id)
                    ->orderBy('sort_order')->pluck('option_text');
                return $item;
            });

        return [$bankSections, $bankItems];
    }

    private function syncSurveyItems(int $surveyId, array $items): void
    {
        DB::table('survey_questions')->where('survey_id', $surveyId)->delete();
        DB::table('survey_sections')->where('survey_id', $surveyId)->delete();

        $sectionMap = [];

        foreach ($items as $i => $item) {
            $kind = $item['kind'] ?? 'question';

            if ($kind === 'section') {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $sectionId = DB::table('survey_sections')->insertGetId([
                    'survey_id'   => $surveyId,
                    'title'       => $title,
                    'sort_order'  => $i,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                if (! empty($item['client_id'])) {
                    $sectionMap[$item['client_id']] = $sectionId;
                }

                continue;
            }

            $sectionId = null;
            if (! empty($item['section_ref']) && isset($sectionMap[$item['section_ref']])) {
                $sectionId = $sectionMap[$item['section_ref']];
            }

            $type = $item['question_type'] ?? 'mcq';
            $qId = DB::table('survey_questions')->insertGetId([
                'survey_id'         => $surveyId,
                'survey_section_id' => $sectionId,
                'question_text'     => $item['question_text'] ?? '',
                'question_type'     => $type,
                'sort_order'        => $i,
                'is_required'       => $item['is_required'] ?? false,
                'placeholder'       => $item['placeholder'] ?? null,
                'rating_max'        => $item['rating_max'] ?? 5,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $optionTypes = ['mcq', 'mcq_multi', 'dropdown', 'yes_no', 'matrix'];
            if (in_array($type, $optionTypes, true)) {
                foreach ($item['options'] ?? [] as $j => $optText) {
                    if (! is_string($optText) || trim($optText) === '') {
                        continue;
                    }
                    DB::table('survey_question_options')->insert([
                        'survey_question_id' => $qId,
                        'option_text'        => $optText,
                        'sort_order'         => $j,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }

            if ($type === 'matrix') {
                foreach ($item['rows'] ?? [] as $j => $rowText) {
                    if (! is_string($rowText) || trim($rowText) === '') {
                        continue;
                    }
                    DB::table('survey_matrix_rows')->insert([
                        'survey_question_id' => $qId,
                        'row_text'           => $rowText,
                        'sort_order'         => $j,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadSurveyItems(int $surveyId): array
    {
        $sections = DB::table('survey_sections')
            ->where('survey_id', $surveyId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($s) => (object) [
                'kind'       => 'section',
                'sort_order' => $s->sort_order,
                'id'         => $s->id,
                'client_id'  => 'section-'.$s->id,
                'title'      => $s->title,
            ]);

        $questions = DB::table('survey_questions')
            ->where('survey_id', $surveyId)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($q) {
                $options = DB::table('survey_question_options')
                    ->where('survey_question_id', $q->id)
                    ->orderBy('sort_order')
                    ->pluck('option_text');

                $rows = $q->question_type === 'matrix'
                    ? DB::table('survey_matrix_rows')
                        ->where('survey_question_id', $q->id)
                        ->orderBy('sort_order')
                        ->pluck('row_text')
                    : collect();

                return (object) [
                    'kind'              => 'question',
                    'sort_order'        => $q->sort_order,
                    'id'                => $q->id,
                    'question_text'     => $q->question_text,
                    'question_type'     => $q->question_type,
                    'is_required'       => (bool) $q->is_required,
                    'placeholder'       => $q->placeholder,
                    'rating_max'        => $q->rating_max,
                    'survey_section_id' => $q->survey_section_id ?? null,
                    'section_ref'       => ($q->survey_section_id ?? null) ? 'section-'.$q->survey_section_id : null,
                    'options'           => $options,
                    'rows'              => $rows,
                ];
            });

        return $sections->concat($questions)
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    private function loadQuestionsForDisplay(int $surveyId, bool $withAnalytics = false): Collection
    {
        return DB::table('survey_questions')
            ->where('survey_id', $surveyId)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($q) use ($withAnalytics) {
                $q->options = DB::table('survey_question_options')
                    ->where('survey_question_id', $q->id)
                    ->orderBy('sort_order')
                    ->get();

                if ($q->question_type === 'matrix') {
                    $q->matrix_rows = DB::table('survey_matrix_rows')
                        ->where('survey_question_id', $q->id)
                        ->orderBy('sort_order')
                        ->get();
                }

                if ($withAnalytics) {
                    $q->analytics = $this->buildQuestionAnalytics($q);
                }

                return $q;
            });
    }

    private function buildQuestionAnalytics(object $q): mixed
    {
        if (in_array($q->question_type, ['mcq', 'mcq_multi', 'dropdown', 'yes_no'], true)) {
            $total = $q->question_type === 'mcq_multi'
                ? DB::table('survey_answers')
                    ->where('survey_question_id', $q->id)
                    ->whereNotNull('answer_option_id')
                    ->distinct()
                    ->count('survey_response_id')
                : DB::table('survey_answers')
                    ->where('survey_question_id', $q->id)
                    ->whereNotNull('answer_option_id')
                    ->count();

            return DB::table('survey_answers as a')
                ->join('survey_question_options as o', 'o.id', '=', 'a.answer_option_id')
                ->where('a.survey_question_id', $q->id)
                ->selectRaw('o.option_text, COUNT(*) as count')
                ->groupBy('o.option_text')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => [
                    'label' => $r->option_text,
                    'count' => $r->count,
                    'pct'   => $total > 0 ? round($r->count / $total * 100, 1) : 0,
                ]);
        }

        if ($q->question_type === 'matrix') {
            $rows = DB::table('survey_matrix_rows')
                ->where('survey_question_id', $q->id)
                ->orderBy('sort_order')
                ->get();

            return $rows->map(function ($row) use ($q) {
                $total = DB::table('survey_answers')
                    ->where('survey_question_id', $q->id)
                    ->where('matrix_row_id', $row->id)
                    ->whereNotNull('answer_option_id')
                    ->count();

                $distribution = DB::table('survey_answers as a')
                    ->join('survey_question_options as o', 'o.id', '=', 'a.answer_option_id')
                    ->where('a.survey_question_id', $q->id)
                    ->where('a.matrix_row_id', $row->id)
                    ->selectRaw('o.option_text, COUNT(*) as count')
                    ->groupBy('o.option_text')
                    ->orderByDesc('count')
                    ->get()
                    ->map(fn ($r) => [
                        'label' => $r->option_text,
                        'count' => $r->count,
                        'pct'   => $total > 0 ? round($r->count / $total * 100, 1) : 0,
                    ]);

                return [
                    'row_id'   => $row->id,
                    'row_text' => $row->row_text,
                    'total'    => $total,
                    'options'  => $distribution,
                ];
            })->values();
        }

        if ($q->question_type === 'rating') {
            $answers = DB::table('survey_answers')
                ->where('survey_question_id', $q->id)
                ->whereNotNull('answer_text')
                ->pluck('answer_text')
                ->map(fn ($v) => (float) $v);
            $total = $answers->count();
            $avg = $total > 0 ? round($answers->avg(), 2) : null;

            $dist = [];
            for ($i = 1; $i <= $q->rating_max; $i++) {
                $cnt = $answers->filter(fn ($v) => (int) $v === $i)->count();
                $dist[] = [
                    'label' => $i,
                    'count' => $cnt,
                    'pct'   => $total > 0 ? round($cnt / $total * 100, 1) : 0,
                ];
            }

            return ['avg' => $avg, 'total' => $total, 'distribution' => $dist];
        }

        if ($q->question_type === 'number') {
            $answers = DB::table('survey_answers')
                ->where('survey_question_id', $q->id)
                ->whereNotNull('answer_text')
                ->pluck('answer_text')
                ->map(fn ($v) => (float) $v);
            $total = $answers->count();

            return [
                'total' => $total,
                'avg'   => $total > 0 ? round($answers->avg(), 2) : null,
                'min'   => $total > 0 ? $answers->min() : null,
                'max'   => $total > 0 ? $answers->max() : null,
                'sum'   => $total > 0 ? $answers->sum() : null,
            ];
        }

        if ($q->question_type === 'short_text') {
            return DB::table('survey_answers')
                ->where('survey_question_id', $q->id)
                ->whereNotNull('answer_text')
                ->where('answer_text', '!=', '')
                ->pluck('answer_text')
                ->values();
        }

        return null;
    }

    /**
     * @return array{0: list<string>, 1: list<array<string, mixed>>}
     */
    private function buildExportColumns(Collection $questions, Collection $sections): array
    {
        $headings = ['Submitted At', 'Name', 'Title', 'Company', 'Age', 'Gender'];
        $columnKeys = [];

        foreach ($questions as $q) {
            $sectionTitle = $q->survey_section_id
                ? ($sections->get($q->survey_section_id)?->title ?? null)
                : null;
            $prefix = $sectionTitle ? "[{$sectionTitle}] " : '';

            if ($q->question_type === 'matrix') {
                foreach ($q->matrix_rows ?? [] as $row) {
                    $headings[] = $prefix.'['.($q->question_text ?: 'Matrix').'] '.$row->row_text;
                    $columnKeys[] = [
                        'type'     => 'matrix_row',
                        'question' => $q,
                        'row'      => $row,
                    ];
                }
                continue;
            }

            $headings[] = $prefix.$q->question_text;
            $columnKeys[] = [
                'type'     => 'question',
                'question' => $q,
            ];
        }

        return [$headings, $columnKeys];
    }

    private function formatExportAnswer(object $question, Collection $answers): string
    {
        if ($answers->isEmpty()) {
            return '';
        }

        if ($question->question_type === 'mcq_multi') {
            return $answers
                ->whereNotNull('answer_option_id')
                ->pluck('option_text')
                ->filter()
                ->implode(', ');
        }

        if (in_array($question->question_type, ['mcq', 'dropdown', 'yes_no'], true)) {
            return (string) ($answers->first()->option_text ?? '');
        }

        return (string) ($answers->first()->answer_text ?? '');
    }

    private function formatMatrixRowAnswer(object $question, object $row, Collection $answers): string
    {
        $answer = $answers->firstWhere('matrix_row_id', $row->id);

        return (string) ($answer->option_text ?? '');
    }

    private function normalizeSurveyItems(Request $request): array
    {
        if ($request->has('items')) {
            return $request->input('items', []);
        }

        return collect($request->input('questions', []))
            ->map(fn ($q) => array_merge(['kind' => 'question'], is_array($q) ? $q : []))
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function questionsFromItems(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn ($item) => ($item['kind'] ?? 'question') === 'question'
        ));
    }

    private function storeMatrixAnswers(int $responseId, int $questionId, mixed $answer): void
    {
        if (! is_array($answer)) {
            return;
        }

        $validRowIds = DB::table('survey_matrix_rows')
            ->where('survey_question_id', $questionId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validOptionIds = DB::table('survey_question_options')
            ->where('survey_question_id', $questionId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($answer as $rowId => $optionId) {
            $rowId = (int) $rowId;
            $optionId = (int) $optionId;

            if (! in_array($rowId, $validRowIds, true) || ! in_array($optionId, $validOptionIds, true)) {
                continue;
            }

            DB::table('survey_answers')->insert([
                'survey_response_id'  => $responseId,
                'survey_question_id'  => $questionId,
                'matrix_row_id'       => $rowId,
                'answer_text'         => null,
                'answer_option_id'    => $optionId,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    /** @deprecated kept for reference — replaced by syncSurveyItems */
    private function syncQuestions(int $surveyId, array $questions): void
    {
        $this->syncSurveyItems($surveyId, collect($questions)
            ->map(fn ($q) => array_merge(['kind' => 'question'], $q))
            ->all());
    }

    private function copyFlaggedQuestionsToBank(PortfolioCompany $company, array $questions): void
    {
        $flagged = array_values(array_filter(
            $questions,
            fn ($q) => is_array($q) && filter_var($q['save_to_bank'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ));

        if ($flagged === []) {
            return;
        }
        $orgId = $company->organization_id;
        $validSectionIds = DB::table('question_bank_sections')
            ->where('organization_id', $orgId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($flagged as $q) {
            $text = trim((string) ($q['question_text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $sectionId = $q['bank_section_id'] ?? null;
            $sectionId = $sectionId === '' || $sectionId === null ? null : (int) $sectionId;
            if ($sectionId && ! in_array($sectionId, $validSectionIds, true)) {
                $sectionId = null;
            }

            $itemId = DB::table('question_bank_items')->insertGetId([
                'organization_id'          => $orgId,
                'question_bank_section_id' => $sectionId,
                'question_text'            => $text,
                'question_type'            => $q['question_type'] ?? 'mcq',
                'is_required'              => filter_var($q['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'rating_max'               => $q['rating_max'] ?? 5,
                'placeholder'              => $q['placeholder'] ?? null,
                'usage_count'              => 0,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            foreach ($q['options'] ?? [] as $j => $optText) {
                if (! is_string($optText) || trim($optText) === '') {
                    continue;
                }
                DB::table('question_bank_item_options')->insert([
                    'question_bank_item_id' => $itemId,
                    'option_text'           => $optText,
                    'sort_order'            => $j,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }
        }
    }

    private function buildAgeGroups($responses): array
    {
        $groups = ['Under 25' => 0, '25–34' => 0, '35–44' => 0, '45–54' => 0, '55+' => 0, 'Not provided' => 0];
        foreach ($responses as $r) {
            $age = $r->respondent_age;
            if (!$age) { $groups['Not provided']++; continue; }
            if ($age < 25) $groups['Under 25']++;
            elseif ($age < 35) $groups['25–34']++;
            elseif ($age < 45) $groups['35–44']++;
            elseif ($age < 55) $groups['45–54']++;
            else $groups['55+']++;
        }
        return $groups;
    }

    private function storeMultiSelectAnswers(int $responseId, int $questionId, mixed $answer): void
    {
        $optionIds = collect(is_array($answer) ? $answer : [])
            ->flatten()
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($optionIds->isEmpty()) {
            return;
        }

        $validIds = DB::table('survey_question_options')
            ->where('survey_question_id', $questionId)
            ->whereIn('id', $optionIds->all())
            ->pluck('id');

        foreach ($validIds as $optionId) {
            DB::table('survey_answers')->insert([
                'survey_response_id'  => $responseId,
                'survey_question_id'  => $questionId,
                'answer_text'         => null,
                'answer_option_id'    => $optionId,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    private function respondentFieldRules(): array
    {
        return [
            'default_respondent_name'    => 'nullable|string|max:255',
            'default_respondent_title'   => 'nullable|string|max:255',
            'default_respondent_company' => 'nullable|string|max:255',
            'show_respondent_age'        => 'sometimes|boolean',
            'show_respondent_gender'     => 'sometimes|boolean',
        ];
    }

    private function respondentFieldValues(Request $request): array
    {
        return [
            'default_respondent_name'    => $this->filledDefault($request->input('default_respondent_name')),
            'default_respondent_title'   => $this->filledDefault($request->input('default_respondent_title')),
            'default_respondent_company' => $this->filledDefault($request->input('default_respondent_company')),
            'show_respondent_age'        => $request->boolean('show_respondent_age'),
            'show_respondent_gender'     => $request->boolean('show_respondent_gender'),
        ];
    }

    private function filledDefault(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function castRespondentFlags(object $survey): void
    {
        $survey->show_respondent_age = (bool) ($survey->show_respondent_age ?? false);
        $survey->show_respondent_gender = (bool) ($survey->show_respondent_gender ?? false);
    }
}