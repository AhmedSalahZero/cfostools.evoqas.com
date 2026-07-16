<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\KpiDefinition;
use App\Models\KpiTracking;
use App\Models\PortfolioCompany;
use App\Models\CompanyKpiConfig;

class KpiController extends Controller
{
    // ── Helper: get company and check org access ──────────────────────────
    private function getCompany($companyId)
    {
        return $this->authorizeCompany((int) $companyId, 'kpi_tracking');
    }

    
    // ── Helper: get KPI definitions visible to this org ───────────────────
private function getDefinitions($orgId, $companyId = null)  // ← Added companyId param (optional for dashboard)
{
    $query = KpiDefinition::where(function ($q) use ($orgId) {
        $q->whereNull('organization_id')
          ->orWhere('organization_id', $orgId);
    })
    ->orderBy('sort_order');

    if ($companyId) {
        // For Library/Dashboard: Fetch all, but attach per-company is_active
        $definitions = $query->get();

        if ($definitions->isEmpty()) return collect();

        $configs = CompanyKpiConfig::where('portfolio_company_id', $companyId)
            ->whereIn('kpi_definition_id', $definitions->pluck('id'))
            ->pluck('is_active', 'kpi_definition_id')
            ->toArray();

        return $definitions->map(function ($def) use ($configs) {
            $def->is_active = $configs[$def->id] ?? true;  // Default true if no config
            return $def;
        })->filter(fn($def) => $def->is_active);  // Only return active for dashboard
    }

    // For other places: Original active-only
    return $query->where('is_active', true)->get();
}




    // ─────────────────────────────────────────────────────────────────────
    // DASHBOARD  GET /portfolio-companies/{company}/kpi
    // ─────────────────────────────────────────────────────────────────────
    public function dashboard($companyId)
    {
        $company     = $this->getCompany($companyId);
        $definitions = $this->getDefinitions($company->organization_id, $companyId);

// ==================== TEMPORARY DEBUG (remove after fixing) ====================
// if (request()->has('debug')) {
//     $debugData = $definitions->map(fn($d) => [
//         'id'          => $d->id,
//         'name'        => $d->name,
//         'source'      => $d->source,
//         'fs_mapping'  => $d->fs_mapping,
//         'is_active'   => $d->is_active,
//         'unit'        => $d->unit,
//     ])->toArray();

// }
// =============================================================================
        

        // Default period: current month
        $periodType  = request('period_type',  'monthly');
        $periodLabel = request('period_label', now()->format('Y-m'));

        // Load trackings for selected period
        $trackings = KpiTracking::where('company_id', $companyId)
            ->where('period_type',  $periodType)
            ->where('period_label', $periodLabel)
            ->get()
            ->keyBy('kpi_definition_id');

        // Build last 6 periods for trend
        $trendPeriods = $this->getLastPeriods($periodType, $periodLabel, 6);

        $trendData = KpiTracking::where('company_id', $companyId)
            ->where('period_type', $periodType)
            ->whereIn('period_label', $trendPeriods)
            ->get()
            ->groupBy('kpi_definition_id');

        // Merge definitions + trackings into cards array
        $cards = $definitions->map(function ($def) use ($trackings, $trendData, $trendPeriods) {
            $tracking = $trackings->get($def->id);
            $trend    = collect($trendPeriods)->map(function ($p) use ($trendData, $def) {
                $t = $trendData->get($def->id)?->firstWhere('period_label', $p);
                return ['period' => $p, 'actual' => $t?->actual, 'target' => $t?->target];
            });

            return [
                'id'               => $def->id,
                'name'             => $def->name,
                'category'         => $def->category,
                'unit'             => $def->unit,
                'source'           => $def->source,
                'higher_is_better' => $def->higher_is_better,
                'target'           => $tracking?->target,
                'actual'           => $tracking?->actual,
                'variance'         => $tracking?->variance,
                'variance_pct'     => $tracking?->variance_percent,
                'status'           => $tracking?->status ?? 'no_data',
                'notes'            => $tracking?->notes,
                'trend'            => $trend,
            ];
        });

        return inertia('Kpi/Dashboard', [
            'company'      => $company,
            'cards'        => $cards,
            'periodType'   => $periodType,
            'periodLabel'  => $periodLabel,
            'trendPeriods' => $trendPeriods,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ENTRY PAGE  GET /portfolio-companies/{company}/kpi/entry
    // ─────────────────────────────────────────────────────────────────────
    public function entryPage($companyId)
    {
        $company     = $this->getCompany($companyId);
        $definitions = $this->getDefinitions($company->organization_id);

        

        $periodType  = request('period_type',  'monthly');
        $periodLabel = request('period_label', now()->format('Y-m'));

        $trackings = KpiTracking::where('company_id', $companyId)
            ->where('period_type',  $periodType)
            ->where('period_label', $periodLabel)
            ->get()
            ->keyBy('kpi_definition_id');

        $rows = $definitions->map(function ($def) use ($trackings) {
            $tracking = $trackings->get($def->id);
            return [
                'kpi_definition_id' => $def->id,
                'name'              => $def->name,
                'category'          => $def->category,
                'unit'              => $def->unit,
                'source'            => $def->source,
                'higher_is_better'  => $def->higher_is_better,
                'target'            => $tracking?->target,
                'actual'            => $tracking?->actual,
                'notes'             => $tracking?->notes,
                'auto_synced'       => $tracking?->auto_synced ?? false,
            ];
        });

      return inertia('Kpi/Entry', [
            'company'     => [
                'id'                => $company->id,
                'name'              => $company->name,
                'invested_currency' => $company->invested_currency ?? 'USD',
            ],
            'rows'        => $rows,
            'periodType'  => $periodType,
            'periodLabel' => $periodLabel,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SAVE ENTRY  POST /portfolio-companies/{company}/kpi/entry
    // ─────────────────────────────────────────────────────────────────────
    public function saveEntry(Request $request, $companyId)
    {
        $this->getCompany($companyId);

        $request->validate([
            'period_type'  => 'required|in:monthly,quarterly,annual',
            'period_label' => 'required|string',
            'entries'      => 'required|array',
        ]);

        foreach ($request->entries as $entry) {
            // Skip auto-synced KPIs — those are written by the system
            $def = KpiDefinition::find($entry['kpi_definition_id']);
            if (!$def) continue;

            KpiTracking::updateOrCreate(
                [
                    'company_id'        => $companyId,
                    'kpi_definition_id' => $entry['kpi_definition_id'],
                    'period_type'       => $request->period_type,
                    'period_label'      => $request->period_label,
                ],
                [
                    'target'      => $entry['target']  !== '' ? $entry['target']  : null,
                    'actual'      => ($def->source === 'manual' && isset($entry['actual'])) ? ($entry['actual'] !== '' ? $entry['actual'] : null) : null,
                    'notes'       => $entry['notes'] ?? null,
                    'entered_by'  => auth()->id(),
                    'auto_synced' => false,
                ]
            );
        }

        return redirect()->route('kpi.entry', [
            'company'      => $companyId,
            'period_type'  => $request->period_type,
            'period_label' => $request->period_label,
        ])->with('success', 'KPI data saved successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // LIBRARY  GET /portfolio-companies/{company}/kpi/library
    // ─────────────────────────────────────────────────────────────────────
    
public function library($companyId)
{
    $company = $this->getCompany($companyId);

    // ←←← CHANGED: Fetch ALL definitions (active + inactive per company)
    $definitions = KpiDefinition::where(function ($q) use ($company) {
        $q->whereNull('organization_id')
          ->orWhere('organization_id', $company->organization_id);
    })
    ->orderBy('sort_order')
    ->get();

    // Attach per-company is_active (create if missing)
    $definitions->each(function ($def) use ($companyId) {
        $config = CompanyKpiConfig::firstOrCreate(
            [
                'portfolio_company_id' => $companyId,
                'kpi_definition_id'    => $def->id,
            ],
            ['is_active' => $def->is_active]  // Inherit global if new
        );
        $def->is_active = $config->is_active;  // Use per-company value
    });

    return inertia('Kpi/Library', [
        'company'     => $company,
        'definitions' => $definitions,
    ]);
}

    // ─────────────────────────────────────────────────────────────────────
    // ADD CUSTOM KPI  POST /portfolio-companies/{company}/kpi/library
    // ─────────────────────────────────────────────────────────────────────
    public function storeCustom(Request $request, $companyId)
    {
        $company = $this->getCompany($companyId);

        $request->validate([
            'name'             => 'required|string|max:255',
            'category'         => 'required|in:financial,non_financial',
            'unit'             => 'required|in:currency,percentage,number,ratio',
            'higher_is_better' => 'required|boolean',
            'description'      => 'nullable|string',
        ]);

        KpiDefinition::create([
            'organization_id'  => $company->organization_id,
            'name'             => $request->name,
            'category'         => $request->category,
            'unit'             => $request->unit,
            'source'           => 'manual',
            'fs_mapping'       => null,
            'higher_is_better' => $request->higher_is_better,
            'description'      => $request->description,
            'is_active'        => true,
            'sort_order'       => 99,
        ]);

        return redirect()->route('kpi.library', $companyId)
            ->with('success', 'Custom KPI added successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // TOGGLE ACTIVE  PATCH /portfolio-companies/{company}/kpi/library/{definition}
    // ─────────────────────────────────────────────────────────────────────
   
    public function toggleActive($companyId, $definitionId)
{
    $company = $this->getCompany($companyId);
    $def = KpiDefinition::findOrFail($definitionId);

    // Toggle per-company config (create if doesn't exist)
    $config = CompanyKpiConfig::firstOrCreate(
        [
            'portfolio_company_id' => $companyId,
            'kpi_definition_id'    => $definitionId,
        ],
        ['is_active' => true]
    );

    $config->update(['is_active' => !$config->is_active]);

    return back()->with('success', 'KPI updated.');
}

    // ─────────────────────────────────────────────────────────────────────
    // AUTO-SYNC  called internally from FinancialStatementController
    // ─────────────────────────────────────────────────────────────────────
    public static function syncFromStatement($companyId, $orgId, $periodType, $periodLabel, $data)
    {
        // $data = associative array like ['revenue' => 500000, 'ratio_current_ratio' => 1.8, ...]

        $definitions = KpiDefinition::where('source', 'auto_fs')
            ->where('is_active', true)
            ->where(function ($q) use ($orgId) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $orgId);
            })
            ->get();

        foreach ($definitions as $def) {
            if (!$def->fs_mapping || !array_key_exists($def->fs_mapping, $data)) continue;

            $value = $data[$def->fs_mapping];
            if (is_null($value)) continue;

            KpiTracking::updateOrCreate(
                [
                    'company_id'        => $companyId,
                    'kpi_definition_id' => $def->id,
                    'period_type'       => $periodType,
                    'period_label'      => $periodLabel,
                    
                ],
                [
                    'actual'      => $value,
                    'auto_synced' => true,
                    'entered_by'  => auth()->id() ?? null,
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPER: generate last N period labels
    // ─────────────────────────────────────────────────────────────────────
    private function getLastPeriods($type, $currentLabel, $count)
    {
        $periods = [];

        if ($type === 'monthly') {
            $date = \Carbon\Carbon::createFromFormat('Y-m', $currentLabel);
            for ($i = $count - 1; $i >= 0; $i--) {
                $periods[] = $date->copy()->subMonths($i)->format('Y-m');
            }
        } elseif ($type === 'quarterly') {
            // label format: "2025-Q1"
            [$year, $q] = explode('-Q', $currentLabel);
            $qNum = (int)$q;
            for ($i = $count - 1; $i >= 0; $i--) {
                $qCurr = $qNum - $i;
                $yCurr = (int)$year;
                while ($qCurr < 1) { $qCurr += 4; $yCurr--; }
                $periods[] = "{$yCurr}-Q{$qCurr}";
            }
        } elseif ($type === 'annual') {
            $year = (int)$currentLabel;
            for ($i = $count - 1; $i >= 0; $i--) {
                $periods[] = (string)($year - $i);
            }
        }

        return $periods;
    }

    public function updateCustom(Request $request, $companyId, $definitionId)
{
    $company = $this->getCompany($companyId);
    
    $definition = KpiDefinition::findOrFail($definitionId);
    
    // Security: only allow updating custom KPIs (org-specific)
    if (!$definition->organization_id || $definition->organization_id !== $company->organization_id) {
        abort(403, 'You can only edit custom KPIs for this company.');
    }

    $validated = $request->validate([
        'name'             => 'required|string|max:255',
        'category'         => 'required|in:financial,non_financial',
        'unit'             => 'required|in:currency,number,percentage,ratio',
        'higher_is_better' => 'required|boolean',
        'description'      => 'nullable|string|max:1000',
    ]);

    $definition->update($validated);

    return back()->with('success', 'Custom KPI updated successfully.');
}

public function deleteCustom($companyId, $definitionId)
{
    $company = $this->getCompany($companyId);
    
    $definition = KpiDefinition::findOrFail($definitionId);
    
    // Security: only delete custom KPIs
    if (!$definition->organization_id || $definition->organization_id !== $company->organization_id) {
        abort(403, 'You can only delete custom KPIs for this company.');
    }

    // Optional: delete related tracking data
    KpiTracking::where('kpi_definition_id', $definitionId)
        ->where('company_id', $companyId)
        ->delete();

    $definition->delete();

    return back()->with('success', 'Custom KPI deleted successfully.');
}
}