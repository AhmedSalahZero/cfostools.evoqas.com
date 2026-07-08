<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuestionBankController extends Controller
{
    public function index()
    {
        $orgId = auth()->user()->organization_id;

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

        return Inertia::render('QuestionBank/Index', [
            'sections' => $sections,
            'items'    => $items,
        ]);
    }

    // ── Section CRUD ─────────────────────────────────────────────────────────

    public function storeSection(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100', 'color' => 'nullable|string|max:20']);
        $orgId = auth()->user()->organization_id;
        $maxOrder = DB::table('question_bank_sections')->where('organization_id', $orgId)->max('sort_order') ?? 0;

        $id = DB::table('question_bank_sections')->insertGetId([
            'organization_id' => $orgId,
            'name'            => $request->name,
            'color'           => $request->color ?? 'blue',
            'sort_order'      => $maxOrder + 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['id' => $id, 'name' => $request->name, 'color' => $request->color ?? 'blue']);
    }

    public function updateSection(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:100']);
        DB::table('question_bank_sections')->where('id', $id)->update([
            'name'       => $request->name,
            'color'      => $request->color ?? 'blue',
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }

    public function destroySection($id)
    {
        // Move items in this section to "uncategorized"
        DB::table('question_bank_items')->where('question_bank_section_id', $id)
            ->update(['question_bank_section_id' => null]);
        DB::table('question_bank_sections')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Item CRUD ─────────────────────────────────────────────────────────────

    public function storeItem(Request $request)
    {
        $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:mcq,yes_no,rating,short_text,number,dropdown',
        ]);
        $orgId = auth()->user()->organization_id;

        $itemId = DB::table('question_bank_items')->insertGetId([
            'organization_id'          => $orgId,
            'question_bank_section_id' => $request->section_id ?: null,
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
        $request->validate(['question_text' => 'required|string']);

        DB::table('question_bank_items')->where('id', $id)->update([
            'question_bank_section_id' => $request->section_id ?: null,
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
        DB::table('question_bank_items')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function moveItem(Request $request, $id)
    {
        DB::table('question_bank_items')->where('id', $id)->update([
            'question_bank_section_id' => $request->section_id ?: null,
            'updated_at'               => now(),
        ]);
        return response()->json(['success' => true]);
    }
}