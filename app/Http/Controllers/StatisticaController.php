<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Carbon\Carbon;

class StatisticaController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Resolve org — super-admin can pass ?org_id=X
    // ─────────────────────────────────────────────────────────────────────────
    private function resolveOrgId(): int
    {
        $user = Auth::user();
        if ($user->hasRole('super-admin') && request('org_id')) {
            return (int) request('org_id');
        }
        return (int) $user->organization_id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX — list all series for the org
    // GET /organizations/{orgId}/statistica
    // ─────────────────────────────────────────────────────────────────────────
    public function index($orgId)
    {
        $this->authorizeOrg($orgId);

        $series = DB::table('statistica_series')
            ->where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // For each series, attach latest value + entry count + sparkline (last 12 points)
        $seriesIds = $series->pluck('id');
        $entriesBySeries = $seriesIds->isEmpty()
            ? collect()
            : DB::table('statistica_entries')
                ->whereIn('series_id', $seriesIds)
                ->orderBy('entry_date')
                ->get(['series_id', 'entry_date', 'value'])
                ->groupBy('series_id');

        $series = $series->map(function ($s) use ($entriesBySeries) {
            $entries = $entriesBySeries->get($s->id, collect());

            $count = $entries->count();
            $latest = $entries->last();
            $prev   = $count >= 2 ? $entries->get($count - 2) : null;

            $change    = null;
            $changePct = null;
            if ($latest && $prev && $prev->value != 0) {
                $change    = round((float)$latest->value - (float)$prev->value, 4);
                $changePct = round((($latest->value - $prev->value) / abs($prev->value)) * 100, 2);
            }

            // Sparkline: last 20 points
            $sparkline = $entries->slice(-20)->values()->map(fn($e) => [
                'date'  => $e->entry_date,
                'value' => (float) $e->value,
            ]);

            return [
                'id'          => $s->id,
                'name'        => $s->name,
                'category'    => $s->category,
                'unit'        => $s->unit,
                'frequency'   => $s->frequency,
                'color'       => $s->color,
                'description' => $s->description,
                'source'      => $s->source,
                'is_active'   => (bool) $s->is_active,
                'sort_order'  => $s->sort_order,
                'entry_count' => $count,
                'latest_value'=> $latest ? (float) $latest->value : null,
                'latest_date' => $latest ? $latest->entry_date : null,
                'change'      => $change,
                'change_pct'  => $changePct,
                'sparkline'   => $sparkline,
            ];
        });

        $org = DB::table('organizations')->find($orgId);
	
        return Inertia::render('Statistica/Index', [
            'orgId'  => $orgId,
            'org'    => ['id' => $org->id, 'name' => $org->name],
            'series' => $series,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE — create new series
    // POST /organizations/{orgId}/statistica
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request, $orgId)
    {
        $this->authorizeWrite($orgId);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['required', 'string', 'in:fx_rates,oil_energy,commodities,interest_rates,custom'],
            'unit'        => ['nullable', 'string', 'max:30'],
            'frequency'   => ['required', 'in:daily,weekly,monthly,quarterly'],
            'color'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'source'      => ['nullable', 'string', 'max:100'],
        ]);

        $maxOrder = DB::table('statistica_series')
            ->where('organization_id', $orgId)
            ->max('sort_order') ?? 0;

        $id = DB::table('statistica_series')->insertGetId([
            'organization_id' => $orgId,
            'name'        => $validated['name'],
            'category'    => $validated['category'],
            'unit'        => $validated['unit'] ?? '',
            'frequency'   => $validated['frequency'],
            'color'       => $validated['color'] ?? '#3b82f6',
            'description' => $validated['description'] ?? null,
            'source'      => $validated['source'] ?? null,
            'is_active'   => true,
            'sort_order'  => $maxOrder + 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('statistica.show', [$orgId, $id])
            ->with('flash', ['success' => 'Series created successfully.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE — edit series metadata
    // PUT /organizations/{orgId}/statistica/{series}
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, $orgId, $seriesId)
    {
        $this->authorizeWrite($orgId);
        $this->authorizeSeries($orgId, $seriesId);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['required', 'string', 'in:fx_rates,oil_energy,commodities,interest_rates,custom'],
            'unit'        => ['nullable', 'string', 'max:30'],
            'frequency'   => ['required', 'in:daily,weekly,monthly,quarterly'],
            'color'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'source'      => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('statistica_series')
            ->where('id', $seriesId)
            ->update([
                'name'        => $validated['name'],
                'category'    => $validated['category'],
                'unit'        => $validated['unit'] ?? '',
                'frequency'   => $validated['frequency'],
                'color'       => $validated['color'] ?? '#3b82f6',
                'description' => $validated['description'] ?? null,
                'source'      => $validated['source'] ?? null,
                'updated_at'  => now(),
            ]);

        return back()->with('flash', ['success' => 'Series updated.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY — delete series + all entries
    // DELETE /organizations/{orgId}/statistica/{series}
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy($orgId, $seriesId)
    {
        $this->authorizeWrite($orgId);
        $this->authorizeSeries($orgId, $seriesId);

        DB::table('statistica_entries')->where('series_id', $seriesId)->delete();
        DB::table('statistica_series')->where('id', $seriesId)->delete();

        return redirect()->route('statistica.index', $orgId)
            ->with('flash', ['success' => 'Series deleted.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW — series detail page with chart + entries + forecast
    // GET /organizations/{orgId}/statistica/{series}
    // ─────────────────────────────────────────────────────────────────────────
    public function show($orgId, $seriesId)
    {
        $this->authorizeOrg($orgId);
        $this->authorizeSeries($orgId, $seriesId);

        $series = DB::table('statistica_series')->find($seriesId);
        $org    = DB::table('organizations')->find($orgId);

        $entries = DB::table('statistica_entries as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.created_by')
            ->where('e.series_id', $seriesId)
            ->orderBy('e.entry_date')
            ->select('e.id', 'e.entry_date', 'e.value', 'e.notes', 'e.created_at',
                     'u.name as created_by_name')
            ->get()
            ->map(fn($e) => [
                'id'              => $e->id,
                'entry_date'      => $e->entry_date,
                'value'           => (float) $e->value,
                'notes'           => $e->notes,
                'created_at'      => $e->created_at,
                'created_by_name' => $e->created_by_name,
            ]);

        // Build forecast using Holt-Winters double exponential smoothing
        $forecast = $this->buildForecast($entries->toArray(), $series->frequency);

        // Growth rate calculations
        $growth = $this->calcGrowthRates($entries->toArray(), $series->frequency);

        return Inertia::render('Statistica/Show', [
            'orgId'    => $orgId,
            'org'      => ['id' => $org->id, 'name' => $org->name],
            'series'   => [
                'id'          => $series->id,
                'name'        => $series->name,
                'category'    => $series->category,
                'unit'        => $series->unit,
                'frequency'   => $series->frequency,
                'color'       => $series->color,
                'description' => $series->description,
                'source'      => $series->source,
                'is_active'   => (bool) $series->is_active,
            ],
            'entries'  => $entries,
            'forecast' => $forecast,
            'growth'   => $growth,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE ENTRY — add a single data point
    // POST /organizations/{orgId}/statistica/{series}/entries
    // ─────────────────────────────────────────────────────────────────────────
    public function storeEntry(Request $request, $orgId, $seriesId)
    {
        $this->authorizeWrite($orgId);
        $this->authorizeSeries($orgId, $seriesId);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'value'      => ['required', 'numeric'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        // Upsert — if same date exists, update it
        $exists = DB::table('statistica_entries')
            ->where('series_id', $seriesId)
            ->where('entry_date', $validated['entry_date'])
            ->first();

        if ($exists) {
            DB::table('statistica_entries')->where('id', $exists->id)->update([
                'value'      => $validated['value'],
                'notes'      => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('statistica_entries')->insert([
                'series_id'  => $seriesId,
                'entry_date' => $validated['entry_date'],
                'value'      => $validated['value'],
                'notes'      => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('flash', ['success' => 'Entry saved.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE ENTRY
    // PUT /organizations/{orgId}/statistica/{series}/entries/{entry}
    // ─────────────────────────────────────────────────────────────────────────
    public function updateEntry(Request $request, $orgId, $seriesId, $entryId)
    {
        $this->authorizeWrite($orgId);
        $this->authorizeSeries($orgId, $seriesId);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'value'      => ['required', 'numeric'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('statistica_entries')
            ->where('id', $entryId)
            ->where('series_id', $seriesId)
            ->update([
                'entry_date' => $validated['entry_date'],
                'value'      => $validated['value'],
                'notes'      => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

        return back()->with('flash', ['success' => 'Entry updated.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY ENTRY
    // DELETE /organizations/{orgId}/statistica/{series}/entries/{entry}
    // ─────────────────────────────────────────────────────────────────────────
    public function destroyEntry($orgId, $seriesId, $entryId)
    {
        $this->authorizeWrite($orgId);
        $this->authorizeSeries($orgId, $seriesId);

        DB::table('statistica_entries')
            ->where('id', $entryId)
            ->where('series_id', $seriesId)
            ->delete();

        return back()->with('flash', ['success' => 'Entry deleted.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IMPORT EXCEL — bulk upload entries
    // POST /organizations/{orgId}/statistica/{series}/import
    // ─────────────────────────────────────────────────────────────────────────
    public function importExcel(Request $request, $orgId, $seriesId)
    {
        $this->authorizeWrite($orgId);
        $this->authorizeSeries($orgId, $seriesId);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $file     = $request->file('file');
        $ext      = strtolower($file->getClientOriginalExtension());
        $imported = 0;
        $skipped  = 0;
        $rows     = [];

        if ($ext === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle); // skip header row
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2) {
                    $rows[] = ['date' => trim($row[0]), 'value' => trim($row[1])];
                }
            }
            fclose($handle);
        } else {
            // Use PhpSpreadsheet via maatwebsite/excel reader
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $data        = $sheet->toArray(null, true, true, false);
            array_shift($data); // skip header
            foreach ($data as $row) {
                if (empty($row[0]) && empty($row[1])) continue;
                $rows[] = ['date' => trim($row[0] ?? ''), 'value' => trim($row[1] ?? '')];
            }
        }

        foreach ($rows as $row) {
            try {
                $date  = Carbon::parse($row['date'])->format('Y-m-d');
                $value = is_numeric($row['value']) ? (float) $row['value'] : null;
                if (!$value && $value !== 0.0) { $skipped++; continue; }

                $exists = DB::table('statistica_entries')
                    ->where('series_id', $seriesId)
                    ->where('entry_date', $date)
                    ->first();

                if ($exists) {
                    DB::table('statistica_entries')->where('id', $exists->id)
                        ->update(['value' => $value, 'updated_at' => now()]);
                } else {
                    DB::table('statistica_entries')->insert([
                        'series_id'  => $seriesId,
                        'entry_date' => $date,
                        'value'      => $value,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
            }
        }

        return back()->with('flash', ['success' => "{$imported} entries imported, {$skipped} skipped."]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COMPARE PAGE
    // GET /organizations/{orgId}/statistica/compare
    // ─────────────────────────────────────────────────────────────────────────
    public function compare($orgId)
    {
        $this->authorizeOrg($orgId);

        $org = DB::table('organizations')->find($orgId);

        $allSeries = DB::table('statistica_series')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'unit', 'color', 'frequency'])
            ->map(fn($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'category'  => $s->category,
                'unit'      => $s->unit,
                'color'     => $s->color,
                'frequency' => $s->frequency,
            ]);

        // Pre-load data for selected series from query string
        $selectedIds = array_filter(array_map('intval', explode(',', request('series', ''))));
        $seriesData  = [];

        foreach ($selectedIds as $sid) {
            $s = DB::table('statistica_series')
                ->where('id', $sid)
                ->where('organization_id', $orgId)
                ->first();
            if (!$s) continue;

            $entries = DB::table('statistica_entries')
                ->where('series_id', $sid)
                ->orderBy('entry_date')
                ->pluck('value', 'entry_date')
                ->map(fn($v) => (float) $v);

            $seriesData[] = [
                'id'      => $s->id,
                'name'    => $s->name,
                'unit'    => $s->unit,
                'color'   => $s->color,
                'entries' => $entries,
            ];
        }

        return Inertia::render('Statistica/Compare', [
            'orgId'      => $orgId,
            'org'        => ['id' => $org->id, 'name' => $org->name],
            'allSeries'  => $allSeries,
            'seriesData' => $seriesData,
            'selected'   => $selectedIds,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOAD TEMPLATE — blank Excel for import
    // GET /organizations/{orgId}/statistica/template
    // ─────────────────────────────────────────────────────────────────────────
    public function downloadTemplate($orgId)
    {
        $this->authorizeOrg($orgId);

        $csv = "Date,Value,Notes\n";
        $csv .= "2026-01-01,30.50,Optional note\n";
        $csv .= "2026-01-02,30.55,\n";
        $csv .= "2026-01-03,30.48,\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="statistica_import_template.csv"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — Holt-Winters Double Exponential Smoothing Forecast
    // ─────────────────────────────────────────────────────────────────────────
    private function buildForecast(array $entries, string $frequency): array
    {
        if (count($entries) < 4) return [];

        $values = array_column($entries, 'value');
        $dates  = array_column($entries, 'entry_date');
        $n      = count($values);

        // Holt-Winters double exponential smoothing (trend-adjusted)
        $alpha = 0.3;  // level smoothing
        $beta  = 0.1;  // trend smoothing

        $level = $values[0];
        $trend = ($values[min(3, $n-1)] - $values[0]) / min(3, $n-1);

        for ($i = 1; $i < $n; $i++) {
            $prevLevel = $level;
            $level     = $alpha * $values[$i] + (1 - $alpha) * ($prevLevel + $trend);
            $trend     = $beta * ($level - $prevLevel) + (1 - $beta) * $trend;
        }

        // Determine how many future points to project based on frequency
        $steps = match($frequency) {
            'daily'     => 30,   // 30 days
            'weekly'    => 12,   // 12 weeks (~3 months)
            'monthly'   => 6,    // 6 months
            'quarterly' => 4,    // 4 quarters (1 year)
            default     => 12,
        };

        // Step size in days
        $stepDays = match($frequency) {
            'daily'     => 1,
            'weekly'    => 7,
            'monthly'   => 30,
            'quarterly' => 91,
            default     => 1,
        };

        $lastDate   = Carbon::parse(end($dates));
        $forecast   = [];

        // 95% confidence band — grows with horizon (simplified)
        // Use historical std dev as basis
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / $n;
        $stdDev   = sqrt($variance);

        for ($h = 1; $h <= $steps; $h++) {
            $projDate  = $lastDate->copy()->addDays($stepDays * $h);
            $projValue = $level + ($h * $trend);

            // Widen confidence band with horizon
            $bandWidth = $stdDev * 1.96 * sqrt($h / $n * 2 + 0.1);

            $forecast[] = [
                'date'  => $projDate->format('Y-m-d'),
                'value' => round($projValue, 4),
                'upper' => round($projValue + $bandWidth, 4),
                'lower' => round(max(0, $projValue - $bandWidth), 4),
            ];
        }

        return $forecast;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — Growth rate calculations
    // ─────────────────────────────────────────────────────────────────────────
    private function calcGrowthRates(array $entries, string $frequency): array
    {
        if (count($entries) < 2) return [];

        $values = array_column($entries, 'value');
        $dates  = array_column($entries, 'entry_date');
        $n      = count($values);

        // MoM or period-on-period change
        $popChanges = [];
        for ($i = 1; $i < $n; $i++) {
            $prev = (float) $values[$i - 1];
            $curr = (float) $values[$i];
            if ($prev == 0) continue;
            $popChanges[] = [
                'date'   => $dates[$i],
                'change' => round($curr - $prev, 4),
                'pct'    => round((($curr - $prev) / abs($prev)) * 100, 2),
            ];
        }

        // YoY — compare to same point 365 days ago (for daily/monthly)
        $yoyChanges = [];
        $dateMap    = array_combine($dates, $values);
        foreach ($entries as $e) {
            $d    = Carbon::parse($e['entry_date']);
            $past = $d->copy()->subYear()->format('Y-m-d');
            if (isset($dateMap[$past]) && $dateMap[$past] != 0) {
                $curr  = (float) $e['value'];
                $prev  = (float) $dateMap[$past];
                $yoyChanges[] = [
                    'date' => $e['entry_date'],
                    'pct'  => round((($curr - $prev) / abs($prev)) * 100, 2),
                ];
            }
        }

        return [
            'pop' => $popChanges,   // period-on-period
            'yoy' => $yoyChanges,   // year-on-year
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — auth helpers
    // ─────────────────────────────────────────────────────────────────────────
    private function authorizeOrg($orgId): void
    {
        $org = DB::table('organizations')->find($orgId);
        abort_unless($org, 404);

        $user = Auth::user();
        if ($user->hasRole('super-admin')) {
            return;
        }
        if ((int) $user->organization_id !== (int) $orgId) {
            abort(403);
        }

        if ($user->hasRole('admin')) {
            return;
        }

        $hasStatisticaAccess = DB::table('user_company_permissions as ucp')
            ->join('portfolio_companies as pc', 'pc.id', '=', 'ucp.portfolio_company_id')
            ->where('ucp.user_id', $user->id)
            ->where('ucp.permission', 'statistica')
            ->where('pc.organization_id', (int) $orgId)
            ->exists();

        abort_unless($hasStatisticaAccess, 403);
    }

    private function authorizeWrite($orgId): void
    {
        $user = Auth::user();
        if ($user->hasRole('super-admin')) return;
        if ((int) $user->organization_id !== (int) $orgId) abort(403);
        if (!$user->hasRole('admin')) abort(403, 'Only admins can modify Statistica data.');
    }

    private function authorizeSeries($orgId, $seriesId): void
    {
        $series = DB::table('statistica_series')
            ->where('id', (int) $seriesId)
            ->where('organization_id', (int) $orgId)
            ->first();
        if (!$series) abort(404, 'Series not found.');
    }
}
