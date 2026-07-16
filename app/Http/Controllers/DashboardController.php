<?php

namespace App\Http\Controllers;

use App\Models\CustomerContract;
use App\Models\PortfolioCompany;
use App\Models\Organization;
use App\Services\ContractAlertService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = Auth::user();

        if (!$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return redirect()->route('portfolio-companies.index');
        }

        // ── Org resolution (super-admin can switch via ?org_id=) ──────────────
        if ($user->hasRole('super-admin') && request('org_id')) {
            $viewOrgId = (int) request('org_id');
        } else {
            $viewOrgId = (int) $user->organization_id;
        }

        // ── 1. Customers ──────────────────────────────────────────────────────
        $customers = PortfolioCompany::where('organization_id', $viewOrgId)
            ->where(fn($q) => $q->where('type', '!=', 'prospect')->orWhereNull('type'))
            ->get();

        $customerIds = $customers->pluck('id')->toArray();

        // ── 2. Contracts ──────────────────────────────────────────────────────
        $contracts = CustomerContract::where('organization_id', $viewOrgId)
            ->with(['services.milestones', 'portfolioCompany'])
            ->get();

        // ── 3. Summary metrics ───────────────────────────────────────────────
        $runningContracts  = $contracts->where('status', 'running');
        $finishedContracts = $contracts->where('status', 'finished');
        $draftContracts    = $contracts->where('status', 'draft');

        // Active customers = those with at least one running contract
        $activeCustomerIds = $runningContracts->pluck('portfolio_company_id')->unique()->values();

        $expiredContracts = app(ContractAlertService::class)->expiredUnfinished($viewOrgId);

        $summary = [
            'total_customers'      => $customers->count(),
            'active_customers'     => $activeCustomerIds->count(),
            'customers_at_risk'    => $customers->where('status', 'at_risk')->count(),
            'total_contracts'      => $contracts->count(),
            'active_contracts'     => $runningContracts->count(),
            'finished_contracts'   => $finishedContracts->count(),
            'draft_contracts'      => $draftContracts->count(),
            'expired_contracts'    => $expiredContracts->count(),
            'active_contract_value'=> (float) $runningContracts->sum('amount'),
            'total_contract_value' => (float) $contracts->sum('amount'),
        ];

        // ── 4. Active customers detail (for modal) ────────────────────────────
        $activeCustomers = $customers->whereIn('id', $activeCustomerIds)->map(function ($c) use ($runningContracts) {
            $myRunning = $runningContracts->where('portfolio_company_id', $c->id);
            return [
                'id'              => $c->id,
                'name'            => $c->name,
                'sector'          => $c->sector,
                'status'          => $c->status,
                'running_count'   => $myRunning->count(),
                'running_value'   => (float) $myRunning->sum('amount'),
                'running_currency'=> $myRunning->first()?->currency ?? 'EGP',
            ];
        })->values()->all();

        // ── 5. Active contracts detail (for modal) ────────────────────────────
        $activeContracts = $runningContracts->map(function ($c) {
            return [
                'id'           => $c->id,
                'name'         => $c->name,
                'code'         => $c->code,
                'customer_id'  => $c->portfolio_company_id,
                'customer_name'=> $c->portfolioCompany?->name ?? '—',
                'start_date'   => $c->start_date?->format('Y-m-d'),
                'end_date'     => $c->end_date?->format('Y-m-d'),
                'amount'       => (float) $c->amount,
                'currency'     => $c->currency,
                'status'       => $c->status,
                'services'     => $c->services->map(fn($s) => [
                    'name'       => $s->name,
                    'amount'     => (float) $s->amount,
                    'start_date' => $s->start_date?->format('Y-m-d'),
                    'end_date'   => $s->end_date?->format('Y-m-d'),
                    'execution_total_pct' => (float) $s->milestones->sum('execution_percentage'),
                ])->values()->all(),
            ];
        })->values()->all();

        // ── 6. Recent contracts (last 10) ─────────────────────────────────────
        $recentContracts = $contracts->sortByDesc('created_at')->take(10)->map(function ($c) {
            return [
                'id'            => $c->id,
                'name'          => $c->name,
                'code'          => $c->code,
                'customer_id'   => $c->portfolio_company_id,
                'customer_name' => $c->portfolioCompany?->name ?? '—',
                'amount'        => (float) $c->amount,
                'currency'      => $c->currency,
                'status'        => $c->status,
                'services_count'=> $c->services->count(),
                'created_at'    => $c->created_at?->format('Y-m-d'),
            ];
        })->values()->all();

        // ── 7. Customers list (for Customers tab) ─────────────────────────────
        $customerList = $customers->map(function ($c) use ($contracts) {
            $myContracts = $contracts->where('portfolio_company_id', $c->id);
            return [
                'id'              => $c->id,
                'name'            => $c->name,
                'sector'          => $c->sector,
                'status'          => $c->status,
                'total_contracts' => $myContracts->count(),
                'running_count'   => $myContracts->where('status', 'running')->count(),
                'total_value'     => (float) $myContracts->sum('amount'),
            ];
        })->values()->all();

        // ── 8. All contracts (for Contracts tab) ─────────────────────────────
        $allContracts = $contracts->map(function ($c) {
            return [
                'id'            => $c->id,
                'name'          => $c->name,
                'code'          => $c->code,
                'customer_id'   => $c->portfolio_company_id,
                'customer_name' => $c->portfolioCompany?->name ?? '—',
                'start_date'    => $c->start_date?->format('Y-m-d'),
                'end_date'      => $c->end_date?->format('Y-m-d'),
                'amount'        => (float) $c->amount,
                'currency'      => $c->currency,
                'status'        => $c->status,
                'services_count'=> $c->services->count(),
                'services'      => $c->services->map(fn($s) => [
                    'name'   => $s->name,
                    'amount' => (float) $s->amount,
                ])->values()->all(),
            ];
        })->sortByDesc('created_at')->values()->all();

        // ── 9. Sector breakdown ───────────────────────────────────────────────
        $sectorBreakdown = $customers->groupBy('sector')->map(fn($g, $sector) => [
            'sector' => $sector ?: 'Other',
            'count'  => $g->count(),
        ])->values()->sortByDesc('count')->values()->all();

        // ── 10. Recent activity (from existing tables) ───────────────────────
        $recentActivity = [];

        // Contract creates
        foreach ($contracts->sortByDesc('created_at')->take(15) as $c) {
            $recentActivity[] = [
                'type'    => 'contract',
                'label'   => "New contract: {$c->name}",
                'sub'     => $c->portfolioCompany?->name ?? '',
                'date'    => $c->created_at?->format('Y-m-d'),
                'status'  => $c->status,
            ];
        }

        usort($recentActivity, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        $recentActivity = array_slice($recentActivity, 0, 15);

        // ── 11. Orgs (super-admin switcher) ───────────────────────────────────
        $allOrgs = $user->hasRole('super-admin')
            ? Organization::orderBy('name')->get(['id', 'name'])->toArray()
            : [];

        $org = Organization::find($viewOrgId);

        return Inertia::render('Dashboard', [
            'summary'           => $summary,
            'activeCustomers'   => $activeCustomers,
            'activeContracts'   => $activeContracts,
            'expiredContracts'  => $expiredContracts->values()->all(),
            'recentContracts'   => $recentContracts,
            'customerList'    => $customerList,
            'allContracts'    => $allContracts,
            'sectorBreakdown' => $sectorBreakdown,
            'recentActivity'  => $recentActivity,
            'org'             => ['name' => $org?->name ?? ''],
            'allOrgs'         => $allOrgs,
            'currentOrgId'    => $viewOrgId,
        ]);
    }
}
