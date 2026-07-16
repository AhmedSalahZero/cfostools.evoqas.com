<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PortfolioCompany;
use App\Models\FinancialStatement;
use App\Models\FsLineItem;
use App\Models\FsSection;
use App\Models\FsSettlementSchedule;
use App\Models\CashForecastEntry;
use Carbon\Carbon;

class CashForecastController extends Controller
{
    private function authorizeCashForecast(PortfolioCompany $company): PortfolioCompany
    {
        return $this->authorizeCompany($company, 'cash_forecast');
    }

    private function clientError(string $logContext, \Throwable $e): \Illuminate\Http\JsonResponse
    {
        \Log::error("{$logContext}: " . $e->getMessage(), ['exception' => $e]);

        return response()->json([
            'error' => 'Something went wrong. Please try again or contact support.',
        ], 500);
    }

    // ─────────────────────────────────────────────────────────
    // SETTLEMENT SCHEDULE — save/update for one line item
    // POST /portfolio-companies/{company}/cash-forecast/settlement
    // ─────────────────────────────────────────────────────────
    public function saveSettlement(Request $request, PortfolioCompany $company)
    {
        $this->authorizeCashForecast($company);
        try {
            $validated = $request->validate([
                'line_item_id'      => 'required|integer',
                'schedule'          => 'required|array',
                'schedule.*.month'  => 'required|string',
                'schedule.*.amount' => 'required|numeric',
                'schedule.*.notes'  => 'nullable|string|max:500',
            ]);

            $lineItemId = $request->input('line_item_id');

            // Verify line item exists and belongs to this company via section → statement
            $lineItem = FsLineItem::findOrFail($lineItemId);
            $section  = \App\Models\FsSection::findOrFail($lineItem->fs_section_id);
            $statement = FinancialStatement::findOrFail($section->financial_statement_id);

            if ((int) $statement->portfolio_company_id !== (int) $company->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Delete old schedules and re-insert non-zero rows
            FsSettlementSchedule::where('fs_line_item_id', $lineItemId)->delete();

            $inserted = 0;
            foreach ($request->input('schedule', []) as $row) {
                $amount = floatval($row['amount'] ?? 0);
                if ($amount == 0) continue;
                FsSettlementSchedule::create([
                    'fs_line_item_id' => $lineItemId,
                    'month'           => $row['month'],
                    'amount'          => $amount,
                    'notes'           => $row['notes'] ?? null,
                ]);
                $inserted++;
            }

            return response()->json(['ok' => true, 'inserted' => $inserted]);

        } catch (\Exception $e) {
            return $this->clientError('saveSettlement', $e);
        }
    }

    // ─────────────────────────────────────────────────────────
    // GET settlement for a specific line item
    // GET /portfolio-companies/{company}/cash-forecast/settlement/{lineItem}
    // ─────────────────────────────────────────────────────────
    public function getSettlement(PortfolioCompany $company, FsLineItem $lineItem)
    {
        // Verify ownership safely without relying on relationship chain
        $section   = \App\Models\FsSection::find($lineItem->fs_section_id);
        $statement = $section ? FinancialStatement::find($section->financial_statement_id) : null;
        if (!$statement || (int) $statement->portfolio_company_id !== (int) $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $schedules = FsSettlementSchedule::where('fs_line_item_id', $lineItem->id)
            ->orderBy('month')
            ->get()
            ->map(fn($s) => [
                'month'  => $s->month,
                'amount' => $s->amount,
                'notes'  => $s->notes,
            ]);

        return response()->json([
            'line_item_id' => $lineItem->id,
            'label'        => $lineItem->label,
            'amount'       => (float) $lineItem->amount,
            'schedule'     => $schedules,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // MAIN FORECAST PAGE
    // GET /portfolio-companies/{company}/cash-forecast
    // GET /portfolio-companies/{company}/financial-statements/{statement}/cash-forecast
    // ─────────────────────────────────────────────────────────
    public function index(Request $request, PortfolioCompany $company, ?FinancialStatement $statement = null)
    {
        // ── Available statements for the selector ──
        $allStatements = FinancialStatement::where('portfolio_company_id', $company->id)
            ->orderByDesc('period_to')
            ->get()
            ->map(fn($s) => [
                'id'    => $s->id,
                'label' => Carbon::parse($s->period_from)->format('M Y')
                         . ' — '
                         . Carbon::parse($s->period_to)->format('M Y'),
                'period_to' => $s->period_to->format('Y-m-d'),
            ]);

        // Use the statement passed in URL, or the latest one
        $activeStatement = $statement
            ?? FinancialStatement::where('portfolio_company_id', $company->id)
                ->orderByDesc('period_to')
                ->first();

        // Forecast horizon: 12 months starting from statement period_to
        $horizonStart = $activeStatement
            ? Carbon::parse($activeStatement->period_to)->addMonth()->startOfMonth()
            : Carbon::now()->startOfMonth();

        $forecastMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $forecastMonths[] = $horizonStart->copy()->addMonths($i)->format('Y-m');
        }

        // ── Pull settlement schedules from balance sheet ──
        // Only from sections: current_assets, current_liabilities, non_current_liabilities
        $settlementSections = ['current_assets', 'current_liabilities', 'non_current_liabilities'];

        $settlementData = []; // [ { section_key, label, line_item_label, line_item_id, schedules: [{month,amount}] } ]

        if ($activeStatement) {
            $sections = FsSection::where('financial_statement_id', $activeStatement->id)
                ->whereIn('section_key', $settlementSections)
                ->with(['lineItems.settlementSchedules'])
                ->get();

            foreach ($sections as $sec) {
                foreach ($sec->lineItems as $li) {
                    $schedules = $li->settlementSchedules
                        ->sortBy('month')
                        ->map(fn($s) => ['month' => $s->month, 'amount' => $s->amount])
                        ->values()
                        ->toArray();

                    if (!empty($schedules)) {
                        $settlementData[] = [
                            'section_key'      => $sec->section_key,
                            'section_label'    => $sec->display_name,
                            'line_item_id'     => $li->id,
                            'line_item_label'  => $li->label,
                            'line_item_amount' => (float) $li->amount,
                            'schedules'        => $schedules,
                            // cash_in if current_assets, cash_out if liabilities
                            'cash_direction'   => $sec->section_key === 'current_assets' ? 'in' : 'out',
                        ];
                    }
                }
            }
        }

        // ── Pull manual forecast entries for this company ──
        $manualEntries = CashForecastEntry::where('portfolio_company_id', $company->id)
            ->when($activeStatement, fn($q) => $q->where(function ($q2) use ($activeStatement) {
                $q2->where('financial_statement_id', $activeStatement->id)
                   ->orWhereNull('financial_statement_id');
            }))
            ->orderBy('type')
            ->orderBy('month')
            ->get()
            ->map(fn($e) => [
                'id'                  => $e->id,
                'type'                => $e->type,
                'category'            => $e->category,
                'description'         => $e->description,
                'amount'              => $e->amount,
                'month'               => $e->month,
                'is_recurring'        => $e->is_recurring,
                'recurring_end_month' => $e->recurring_end_month,
                'notes'               => $e->notes,
            ]);

        // ── Build monthly forecast grid ──
        // For each forecast month: sum cash in and cash out from settlements + manual entries
        $grid = [];
        foreach ($forecastMonths as $m) {
            $grid[$m] = ['cash_in' => 0.0, 'cash_out' => 0.0];
        }

        // Settlement-driven flows
        foreach ($settlementData as $item) {
            foreach ($item['schedules'] as $sch) {
                if (!isset($grid[$sch['month']])) continue;
                if ($item['cash_direction'] === 'in') {
                    $grid[$sch['month']]['cash_in'] += $sch['amount'];
                } else {
                    $grid[$sch['month']]['cash_out'] += $sch['amount'];
                }
            }
        }

        // Manual entry flows (expand recurring)
        foreach ($manualEntries as $entry) {
            $entryModel = CashForecastEntry::find($entry['id']);
            foreach ($entryModel->expandedMonths() as $em) {
                if (!isset($grid[$em['month']])) continue;
                if ($entry['type'] === 'in') {
                    $grid[$em['month']]['cash_in'] += $em['amount'];
                } else {
                    $grid[$em['month']]['cash_out'] += $em['amount'];
                }
            }
        }

        // Build final grid array with net + accumulated
        $accumulated = 0.0;
        $gridArray   = [];
        foreach ($forecastMonths as $m) {
            $in   = round($grid[$m]['cash_in'], 2);
            $out  = round($grid[$m]['cash_out'], 2);
            $net  = $in - $out;
            $accumulated += $net;
            $gridArray[] = [
                'month'       => $m,
                'label'       => Carbon::parse($m . '-01')->format('M Y'),
                'cash_in'     => $in,
                'cash_out'    => $out,
                'net'         => round($net, 2),
                'accumulated' => round($accumulated, 2),
            ];
        }

        return Inertia::render('CashForecast/Index', [
            'company'           => ['id' => $company->id, 'name' => $company->name, 'currency' => $company->invested_currency ?? 'USD'],
            'allStatements'     => $allStatements,
            'activeStatement'   => $activeStatement ? ['id' => $activeStatement->id, 'label' => Carbon::parse($activeStatement->period_from)->format('M Y') . ' — ' . Carbon::parse($activeStatement->period_to)->format('M Y')] : null,
            'forecastMonths'    => $forecastMonths,
            'settlementData'    => $settlementData,
            'manualEntries'     => $manualEntries,
            'grid'              => $gridArray,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // SAVE manual forecast entry
    // POST /portfolio-companies/{company}/cash-forecast/entries
    // ─────────────────────────────────────────────────────────
    public function storeEntry(Request $request, PortfolioCompany $company)
    {
        $request->validate([
            'type'                => 'required|in:in,out',
            'category'            => 'required|in:operating,investing,financing',
            'description'         => 'required|string|max:255',
            'amount'              => 'required|numeric|min:0.01',
            'month'               => 'required|date_format:Y-m',
            'is_recurring'        => 'boolean',
            'recurring_end_month' => 'nullable|date_format:Y-m|after_or_equal:month',
            'notes'               => 'nullable|string|max:1000',
            'financial_statement_id' => 'nullable|exists:financial_statements,id',
        ]);

        $entry = CashForecastEntry::create([
            'portfolio_company_id'   => $company->id,
            'financial_statement_id' => $request->financial_statement_id,
            'type'                   => $request->type,
            'category'               => $request->category,
            'description'            => $request->description,
            'amount'                 => $request->amount,
            'month'                  => $request->month,
            'is_recurring'           => $request->boolean('is_recurring'),
            'recurring_end_month'    => $request->recurring_end_month,
            'notes'                  => $request->notes,
            'created_by'             => auth()->id(),
        ]);

        return response()->json(['ok' => true, 'id' => $entry->id]);
    }

    // ─────────────────────────────────────────────────────────
    // UPDATE manual entry
    // PUT /portfolio-companies/{company}/cash-forecast/entries/{entry}
    // ─────────────────────────────────────────────────────────
    public function updateEntry(Request $request, PortfolioCompany $company, CashForecastEntry $entry)
    {
        abort_unless($entry->portfolio_company_id === $company->id, 403);

        $request->validate([
            'type'                => 'required|in:in,out',
            'category'            => 'required|in:operating,investing,financing',
            'description'         => 'required|string|max:255',
            'amount'              => 'required|numeric|min:0.01',
            'month'               => 'required|date_format:Y-m',
            'is_recurring'        => 'boolean',
            'recurring_end_month' => 'nullable|date_format:Y-m|after_or_equal:month',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $entry->update([
            'type'                => $request->type,
            'category'            => $request->category,
            'description'         => $request->description,
            'amount'              => $request->amount,
            'month'               => $request->month,
            'is_recurring'        => $request->boolean('is_recurring'),
            'recurring_end_month' => $request->recurring_end_month,
            'notes'               => $request->notes,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────
    // DELETE manual entry
    // DELETE /portfolio-companies/{company}/cash-forecast/entries/{entry}
    // ─────────────────────────────────────────────────────────
    public function destroyEntry(PortfolioCompany $company, CashForecastEntry $entry)
    {
        abort_unless($entry->portfolio_company_id === $company->id, 403);
        $entry->delete();
        return response()->json(['ok' => true]);
    }


 







}