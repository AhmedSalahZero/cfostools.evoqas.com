<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuestionBankController extends Controller
{
    public function index()
    {
        $orgId = $this->organizationId();

        $sections = DB::table('question_bank_sections')
            ->where('organization_id', $orgId)
            ->orderBy('sort_order')->get();

        $items = DB::table('question_bank_items as qi')
            ->leftJoin('question_bank_sections as s', 's.id', '=', 'qi.question_bank_section_id')
            ->where('qi.organization_id', $orgId)
            ->select('qi.*', 's.name as section_name', 's.color as section_color')
            ->orderBy('qi.question_bank_section_id')->orderByDesc('qi.usage_count')
            ->get()
            ->map(function ($item) {
                $item->options = DB::table('question_bank_item_options')
                    ->where('question_bank_item_id', $item->id)
                    ->orderBy('sort_order')->pluck('option_text');
                return $item;
            });

        session(['question_bank_organization_id' => $orgId]);

        $currentOrg = DB::table('organizations')->where('id', $orgId)->first();

        return Inertia::render('QuestionBank/Index', [
            'sections' => $sections,
            'items'    => $items,
            'organizationId' => $orgId,
            'organizationName' => $currentOrg->name ?? null,
            'organizations' => $this->accessibleOrganizations(),
        ]);
    }

    // ── Section CRUD ─────────────────────────────────────────────────────────

    public function storeSection(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);
        $orgId = $this->organizationId();
        $maxOrder = DB::table('question_bank_sections')->where('organization_id', $orgId)->max('sort_order') ?? 0;
        $color = $this->normalizeSectionColor($request->color);

        $id = DB::table('question_bank_sections')->insertGetId([
            'organization_id' => $orgId,
            'name'            => $request->name,
            'color'           => $color,
            'sort_order'      => $maxOrder + 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['id' => $id, 'name' => $request->name, 'color' => $color]);
    }

    public function updateSection(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);
        $orgId = $this->organizationId();
        $exists = DB::table('question_bank_sections')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->exists();
        abort_unless($exists, 404);

        $color = $this->normalizeSectionColor($request->color);
        DB::table('question_bank_sections')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->update([
                'name'       => $request->name,
                'color'      => $color,
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    public function destroySection($id)
    {
        $orgId = $this->organizationId();
        $section = DB::table('question_bank_sections')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->first();
        abort_unless($section, 404);

        DB::table('question_bank_items')
            ->where('organization_id', $orgId)
            ->where('question_bank_section_id', $id)
            ->update(['question_bank_section_id' => null]);
        DB::table('question_bank_sections')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->delete();

        return response()->json(['success' => true]);
    }

    // ── Item CRUD ─────────────────────────────────────────────────────────────

    public function storeItem(Request $request)
    {
        $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:mcq,mcq_multi,yes_no,rating,short_text,number,dropdown',
        ]);
        $orgId = $this->organizationId();
        $sectionId = $this->sectionIdInOrg($request->section_id, $orgId);

        $itemId = DB::table('question_bank_items')->insertGetId([
            'organization_id'          => $orgId,
            'question_bank_section_id' => $sectionId,
            'question_text'            => $request->question_text,
            'question_type'            => $request->question_type,
            'is_required'              => $request->boolean('is_required'),
            'rating_max'               => $request->rating_max ?? 5,
            'placeholder'              => $request->placeholder,
            'usage_count'              => 0,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        foreach ($request->options ?? [] as $i => $text) {
            if (trim($text) === '') continue;
            DB::table('question_bank_item_options')->insert([
                'question_bank_item_id' => $itemId,
                'option_text'           => $text,
                'sort_order'            => $i,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        return response()->json(['success' => true, 'id' => $itemId]);
    }

    public function updateItem(Request $request, $id)
    {
        $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'sometimes|in:mcq,mcq_multi,yes_no,rating,short_text,number,dropdown',
        ]);

        $orgId = $this->organizationId();
        $this->assertItemInOrg($id, $orgId);
        $sectionId = $this->sectionIdInOrg($request->section_id, $orgId);

        DB::table('question_bank_items')->where('id', $id)->where('organization_id', $orgId)->update([
            'question_bank_section_id' => $sectionId,
            'question_text'            => $request->question_text,
            'question_type'            => $request->question_type,
            'is_required'              => $request->boolean('is_required'),
            'rating_max'               => $request->rating_max ?? 5,
            'placeholder'              => $request->placeholder,
            'updated_at'               => now(),
        ]);

        DB::table('question_bank_item_options')->where('question_bank_item_id', $id)->delete();
        foreach ($request->options ?? [] as $i => $text) {
            if (trim($text) === '') continue;
            DB::table('question_bank_item_options')->insert([
                'question_bank_item_id' => $id,
                'option_text'           => $text,
                'sort_order'            => $i,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function destroyItem($id)
    {
        $orgId = $this->organizationId();
        $this->assertItemInOrg($id, $orgId);
        DB::table('question_bank_items')->where('id', $id)->where('organization_id', $orgId)->delete();

        return response()->json(['success' => true]);
    }

    public function moveItem(Request $request, $id)
    {
        $orgId = $this->organizationId();
        $this->assertItemInOrg($id, $orgId);
        $sectionId = $this->sectionIdInOrg($request->section_id, $orgId);

        DB::table('question_bank_items')->where('id', $id)->where('organization_id', $orgId)->update([
            'question_bank_section_id' => $sectionId,
            'updated_at'               => now(),
        ]);
        return response()->json(['success' => true]);
    }

    private function organizationId(): int
    {
        $user = auth()->user();
        $requested = request()->input('organization_id');

        if ($requested !== null && $requested !== '') {
            $requested = (int) $requested;
            if ($requested > 0 && $this->canAccessOrganization($requested)) {
                return $requested;
            }
        }

        $sessionOrg = (int) session('question_bank_organization_id');
        if ($sessionOrg > 0 && $this->canAccessOrganization($sessionOrg)) {
            return $sessionOrg;
        }

        if ($user->organization_id) {
            return (int) $user->organization_id;
        }

        $fromAssignment = DB::table('user_company_assignments as uca')
            ->join('portfolio_companies as pc', 'pc.id', '=', 'uca.portfolio_company_id')
            ->where('uca.user_id', $user->id)
            ->orderBy('uca.id')
            ->value('pc.organization_id');

        if ($fromAssignment) {
            return (int) $fromAssignment;
        }

        if ($user->hasRole('super-admin')) {
            $first = DB::table('organizations')->orderBy('id')->value('id');
            if ($first) {
                return (int) $first;
            }
        }

        abort(422, 'Your account is not linked to an organization.');
    }

    private function canAccessOrganization(int $orgId): bool
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin')) {
            return DB::table('organizations')->where('id', $orgId)->exists();
        }

        if ((int) $user->organization_id === $orgId) {
            return true;
        }

        return DB::table('user_company_assignments as uca')
            ->join('portfolio_companies as pc', 'pc.id', '=', 'uca.portfolio_company_id')
            ->where('uca.user_id', $user->id)
            ->where('pc.organization_id', $orgId)
            ->exists();
    }

    private function accessibleOrganizations()
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin')) {
            return DB::table('organizations')->orderBy('name')->get(['id', 'name']);
        }

        $ids = collect([(int) $user->organization_id])
            ->merge(
                DB::table('user_company_assignments as uca')
                    ->join('portfolio_companies as pc', 'pc.id', '=', 'uca.portfolio_company_id')
                    ->where('uca.user_id', $user->id)
                    ->pluck('pc.organization_id')
            )
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('organizations')
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function sectionIdInOrg(mixed $sectionId, int $orgId): ?int
    {
        if ($sectionId === null || $sectionId === '' || $sectionId === false) {
            return null;
        }

        $id = (int) $sectionId;
        if ($id < 1) {
            return null;
        }

        $exists = DB::table('question_bank_sections')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->exists();

        abort_unless($exists, 422, 'Section not found.');

        return $id;
    }

    private function assertItemInOrg(int $id, int $orgId): void
    {
        $exists = DB::table('question_bank_items')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->exists();

        abort_unless($exists, 404);
    }

    private function normalizeSectionColor(?string $color): string
    {
        $color = trim((string) $color);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return strtolower($color);
        }

        return [
            'blue' => '#3b82f6',
            'purple' => '#a855f7',
            'green' => '#22c55e',
            'amber' => '#f59e0b',
            'red' => '#ef4444',
            'cyan' => '#06b6d4',
            'rose' => '#f43f5e',
            'indigo' => '#6366f1',
            'teal' => '#14b8a6',
            'orange' => '#f97316',
        ][$color] ?? '#14b8a6';
    }
}