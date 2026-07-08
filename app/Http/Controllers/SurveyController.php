<?php

namespace App\Http\Controllers;

use App\Models\PortfolioCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SurveyController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // INDEX — List all surveys for a company
    // ─────────────────────────────────────────────────────────────────────────
    public function index(PortfolioCompany $company)
    {
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

        return Inertia::render('Surveys/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'surveys' => $surveys,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE — Show builder page
    // ─────────────────────────────────────────────────────────────────────────
    public function create(PortfolioCompany $company)
    {
        $orgId = $company->organization_id;

        $bankSections = DB::table('question_bank_sections')
            ->where('organization_id', $orgId)
            ->orderBy('sort_order')->get();

        $bankItems = DB::table('question_bank_items as qi')
            ->where('qi.organization_id', $orgId)
            ->select('qi.*')
            ->orderBy('qi.question_bank_section_id')->orderByDesc('qi.usage_count')
            ->get()
            ->map(function ($item) {
                $item->options = DB::table('question_bank_item_options')
                    ->where('question_bank_item_id', $item->id)
                    ->orderBy('sort_order')->pluck('option_text');
                return $item;
            });

        return Inertia::render('Surveys/Create', [
            'company'      => ['id' => $company->id, 'name' => $company->name],
            'bankSections' => $bankSections,
            'bankItems'    => $bankItems,
            'survey'       => null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE — Save new survey
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'questions'   => 'nullable|array',
        ]);

        $surveyId = DB::table('surveys')->insertGetId([
            'portfolio_company_id' => $company->id,
            'organization_id'      => $company->organization_id,
            'created_by'           => auth()->id(),
            'title'                => $request->title,
            'introduction'         => $request->introduction,
            'prepared_by'          => $request->prepared_by,
            'status'               => 'draft',
            'is_template'          => $request->boolean('is_template'),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $this->syncQuestions($surveyId, $request->questions ?? []);

        return redirect()->route('surveys.index', $company->id)
            ->with('success', 'Survey created successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT — Show builder with existing data
    // ─────────────────────────────────────────────────────────────────────────
    public function edit(PortfolioCompany $company, $surveyId)
    {
        $survey = DB::table('surveys')->where('id', $surveyId)
            ->where('portfolio_company_id', $company->id)->firstOrFail();

        $questions = DB::table('survey_questions')
            ->where('survey_id', $surveyId)
            ->orderBy('sort_order')->get()
            ->map(function ($q) {
                $q->options = DB::table('survey_question_options')
                    ->where('survey_question_id', $q->id)
                    ->orderBy('sort_order')->pluck('option_text');
                return $q;
            });

        $survey->questions = $questions;

        $orgId = $company->organization_id;
        $bankSections = DB::table('question_bank_sections')
            ->where('organization_id', $orgId)->orderBy('sort_order')->get();

        $bankItems = DB::table('question_bank_items as qi')
            ->where('qi.organization_id', $orgId)->select('qi.*')
            ->orderBy('qi.question_bank_section_id')->orderByDesc('qi.usage_count')
            ->get()->map(function ($item) {
                $item->options = DB::table('question_bank_item_options')
                    ->where('question_bank_item_id', $item->id)
                    ->orderBy('sort_order')->pluck('option_text');
                return $item;
            });

        return Inertia::render('Surveys/Create', [
            'company'      => ['id' => $company->id, 'name' => $company->name],
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
        $request->validate(['title' => 'required|string|max:255']);

        DB::table('surveys')->where('id', $surveyId)->update([
            'title'        => $request->title,
            'introduction' => $request->introduction,
            'prepared_by'  => $request->prepared_by,
            'is_template'  => $request->boolean('is_template'),
            'updated_at'   => now(),
        ]);

        $this->syncQuestions($surveyId, $request->questions ?? []);

        return redirect()->route('surveys.index', $company->id)
            ->with('success', 'Survey updated.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(PortfolioCompany $company, $surveyId)
    {
        DB::table('surveys')->where('id', $surveyId)
            ->where('portfolio_company_id', $company->id)->delete();

        return back()->with('success', 'Survey deleted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLISH — Generate link token and set active
    // ─────────────────────────────────────────────────────────────────────────
    public function publish(PortfolioCompany $company, $surveyId)
    {
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
    public function copy(PortfolioCompany $company, $surveyId)
    {
        $original = DB::table('surveys')->where('id', $surveyId)->firstOrFail();

        $newId = DB::table('surveys')->insertGetId([
            'portfolio_company_id' => $company->id,
            'organization_id'      => $company->organization_id,
            'created_by'           => auth()->id(),
            'title'                => 'Copy of ' . $original->title,
            'introduction'         => $original->introduction,
            'prepared_by'          => $original->prepared_by,
            'status'               => 'draft',
            'link_token'           => null,
            'response_count'       => 0,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $questions = DB::table('survey_questions')
            ->where('survey_id', $surveyId)->orderBy('sort_order')->get();

        foreach ($questions as $q) {
            $newQId = DB::table('survey_questions')->insertGetId([
                'survey_id'     => $newId,
                'question_text' => $q->question_text,
                'question_type' => $q->question_type,
                'sort_order'    => $q->sort_order,
                'is_required'   => $q->is_required,
                'placeholder'   => $q->placeholder,
                'rating_max'    => $q->rating_max,
                'created_at'    => now(),
                'updated_at'    => now(),
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
        }

        return back()->with('success', 'Survey copied successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE TO BANK — Save selected questions to question bank
    // ─────────────────────────────────────────────────────────────────────────
    public function saveToBank(Request $request, PortfolioCompany $company, $surveyId)
    {
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

        $questions = DB::table('survey_questions')
            ->where('survey_id', $survey->id)
            ->orderBy('sort_order')->get()
            ->map(function ($q) {
                $q->options = DB::table('survey_question_options')
                    ->where('survey_question_id', $q->id)
                    ->orderBy('sort_order')->get();
                return $q;
            });

        return Inertia::render('Surveys/Public', [
            'survey'    => $survey,
            'questions' => $questions,
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
            'respondent_name'    => $request->respondent_name,
            'respondent_title'   => $request->respondent_title,
            'respondent_company' => $request->respondent_company,
            'respondent_gender'  => $request->respondent_gender,
            'respondent_age'     => $request->respondent_age ?: null,
            'ip_address'         => $request->ip(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        foreach ($request->answers ?? [] as $qId => $answer) {
            $q = DB::table('survey_questions')->find($qId);
            if (!$q) continue;

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
        $survey = DB::table('surveys')->where('id', $surveyId)
            ->where('portfolio_company_id', $company->id)->firstOrFail();

        $questions = DB::table('survey_questions')
            ->where('survey_id', $surveyId)->orderBy('sort_order')->get()
            ->map(function ($q) use ($surveyId) {
                $q->options = DB::table('survey_question_options')
                    ->where('survey_question_id', $q->id)
                    ->orderBy('sort_order')->get();

                // Build analytics per question type
                if (in_array($q->question_type, ['mcq', 'dropdown', 'yes_no'])) {
                    $total = DB::table('survey_answers')
                        ->where('survey_question_id', $q->id)
                        ->whereNotNull('answer_option_id')->count();

                    $q->analytics = DB::table('survey_answers as a')
                        ->join('survey_question_options as o', 'o.id', '=', 'a.answer_option_id')
                        ->where('a.survey_question_id', $q->id)
                        ->selectRaw('o.option_text, COUNT(*) as count')
                        ->groupBy('o.option_text')->orderByDesc('count')->get()
                        ->map(fn($r) => [
                            'label' => $r->option_text,
                            'count' => $r->count,
                            'pct'   => $total > 0 ? round($r->count / $total * 100, 1) : 0,
                        ]);

                } elseif ($q->question_type === 'rating') {
                    $answers = DB::table('survey_answers')
                        ->where('survey_question_id', $q->id)
                        ->whereNotNull('answer_text')
                        ->pluck('answer_text')->map(fn($v) => (float)$v);
                    $total = $answers->count();
                    $avg   = $total > 0 ? round($answers->avg(), 2) : null;

                    $dist = [];
                    for ($i = 1; $i <= $q->rating_max; $i++) {
                        $cnt = $answers->filter(fn($v) => (int)$v === $i)->count();
                        $dist[] = [
                            'label' => $i,
                            'count' => $cnt,
                            'pct'   => $total > 0 ? round($cnt / $total * 100, 1) : 0,
                        ];
                    }
                    $q->analytics = ['avg' => $avg, 'total' => $total, 'distribution' => $dist];

                } elseif ($q->question_type === 'number') {
                    $answers = DB::table('survey_answers')
                        ->where('survey_question_id', $q->id)
                        ->whereNotNull('answer_text')
                        ->pluck('answer_text')->map(fn($v) => (float)$v);
                    $total = $answers->count();
                    $q->analytics = [
                        'total' => $total,
                        'avg'   => $total > 0 ? round($answers->avg(), 2) : null,
                        'min'   => $total > 0 ? $answers->min() : null,
                        'max'   => $total > 0 ? $answers->max() : null,
                        'sum'   => $total > 0 ? $answers->sum() : null,
                    ];

                } elseif ($q->question_type === 'short_text') {
                    $q->analytics = DB::table('survey_answers')
                        ->where('survey_question_id', $q->id)
                        ->whereNotNull('answer_text')->where('answer_text', '!=', '')
                        ->pluck('answer_text')->values();
                }
                return $q;
            });

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
            'demographics' => $demographics,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function syncQuestions(int $surveyId, array $questions): void
    {
        DB::table('survey_questions')->where('survey_id', $surveyId)->delete();

        foreach ($questions as $i => $q) {
            $qId = DB::table('survey_questions')->insertGetId([
                'survey_id'     => $surveyId,
                'question_text' => $q['question_text'] ?? '',
                'question_type' => $q['question_type'] ?? 'mcq',
                'sort_order'    => $i,
                'is_required'   => $q['is_required'] ?? false,
                'placeholder'   => $q['placeholder'] ?? null,
                'rating_max'    => $q['rating_max'] ?? 5,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            foreach ($q['options'] ?? [] as $j => $optText) {
                if (trim($optText) === '') continue;
                DB::table('survey_question_options')->insert([
                    'survey_question_id' => $qId,
                    'option_text'        => $optText,
                    'sort_order'         => $j,
                    'created_at'         => now(),
                    'updated_at'         => now(),
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
}