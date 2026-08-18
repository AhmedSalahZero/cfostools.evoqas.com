<?php

namespace App\Http\Controllers;

use App\Models\ContractService;
use App\Models\ContractServiceMilestone;
use App\Models\CustomerContract;
use App\Models\PortfolioCompany;
use App\Services\ContractCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerContractController extends Controller
{
    public function index(PortfolioCompany $portfolioCompany)
    {
        $this->authorizeCompany($portfolioCompany, 'contracts');

        $contracts = $portfolioCompany
            ->contracts()
            ->with(['services.milestones'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => $this->formatContract($c));

        return Inertia::render('CustomerContracts/Index', [
            'customer'  => [
                'id'   => $portfolioCompany->id,
                'name' => $portfolioCompany->name,
            ],
            'contracts' => $contracts,
        ]);
    }

    public function create(PortfolioCompany $portfolioCompany)
    {
        return Inertia::render('CustomerContracts/Create', [
            'customer'       => [
                'id'   => $portfolioCompany->id,
                'name' => $portfolioCompany->name,
            ],
            'existingServices' => $this->existingServiceNames($portfolioCompany->organization_id),
        ]);
    }

    public function store(Request $request, PortfolioCompany $portfolioCompany)
    {
        $data = $this->validateContract($request, isCreate: true);

        DB::transaction(function () use ($data, $portfolioCompany) {
            $totalAmount = collect($data['services'])->sum(fn ($s) => (float) $s['amount']);
            $codeGen     = app(ContractCodeGenerator::class);

            $contract = CustomerContract::create([
                'organization_id'      => $portfolioCompany->organization_id,
                'portfolio_company_id' => $portfolioCompany->id,
                'name'                 => $data['name'],
                'code'                 => $codeGen->generate($portfolioCompany),
                'start_date'           => $data['start_date'] ?? null,
                'end_date'             => $data['end_date'] ?? null,
                'amount'               => $totalAmount,
                'currency'             => strtoupper($data['currency']),
                'status'               => 'draft',
                'notes'                => $data['notes'] ?? null,
            ]);

            foreach ($data['services'] as $i => $svc) {
                $this->persistService($contract, $svc, $i, $data, $portfolioCompany);
            }
        });

        return redirect()
            ->route('customer-contracts.index', $portfolioCompany)
            ->with('success', 'Contract created successfully.');
    }

    public function edit(PortfolioCompany $portfolioCompany, CustomerContract $contract)
    {
        $contract->load('services.milestones');

        return Inertia::render('CustomerContracts/Edit', [
            'customer' => [
                'id'   => $portfolioCompany->id,
                'name' => $portfolioCompany->name,
            ],
            'contract'         => $this->formatContract($contract),
            'existingServices' => $this->existingServiceNames($portfolioCompany->organization_id),
        ]);
    }

    public function update(Request $request, PortfolioCompany $portfolioCompany, CustomerContract $contract)
    {
        $data = $this->validateContract($request, isCreate: false);

        DB::transaction(function () use ($data, $contract, $portfolioCompany) {
            $totalAmount = collect($data['services'])->sum(fn ($s) => (float) $s['amount']);

            $contract->update([
                'name'       => $data['name'],
                'start_date' => $data['start_date'] ?? null,
                'end_date'   => $data['end_date'] ?? null,
                'amount'     => $totalAmount,
                'currency'   => strtoupper($data['currency']),
                'notes'      => $data['notes'] ?? null,
            ]);

            $existingServiceIds = $contract->services()->pluck('id')->all();
            $keptIds            = [];

            foreach ($data['services'] as $i => $svc) {
                if (!empty($svc['id']) && in_array((int) $svc['id'], $existingServiceIds, true)) {
                    $contractService = ContractService::find($svc['id']);
                    $contractService->update([
                        'name'        => $svc['name'],
                        'description' => $svc['description'] ?? null,
                        'amount'      => (float) $svc['amount'],
                        'start_date'  => $svc['start_date'] ?? null,
                        'end_date'    => $svc['end_date'] ?? null,
                        'sort_order'  => $i,
                    ]);
                    $contractService->milestones()->delete();
                    $this->saveMilestones($contractService, $svc['milestones'] ?? [], (float) $svc['amount']);
                    $this->syncLinkedProject($contractService, $svc, $data, $portfolioCompany);
                    $keptIds[] = $contractService->id;
                } else {
                    $contractService = $this->persistService($contract, $svc, $i, $data, $portfolioCompany);
                    $keptIds[]       = $contractService->id;
                }
            }

            $removedIds = array_diff($existingServiceIds, $keptIds);
            if ($removedIds) {
                ContractService::whereIn('id', $removedIds)->each(function (ContractService $s) {
                    $s->milestones()->delete();
                    $s->delete();
                });
            }
        });

        return redirect()
            ->route('customer-contracts.index', $portfolioCompany)
            ->with('success', 'Contract updated successfully.');
    }

    public function destroy(PortfolioCompany $portfolioCompany, CustomerContract $contract)
    {
        $contract->services()->each(function (ContractService $s) {
            $s->milestones()->delete();
        });
        $contract->services()->delete();
        $contract->delete();

        return redirect()
            ->route('customer-contracts.index', $portfolioCompany)
            ->with('success', 'Contract deleted.');
    }

    public function markRunning(PortfolioCompany $portfolioCompany, CustomerContract $contract)
    {
        $contract->update(['status' => 'running']);
        return back()->with('success', 'Contract activated.');
    }

    public function markFinished(PortfolioCompany $portfolioCompany, CustomerContract $contract)
    {
        $contract->update(['status' => 'finished']);
        return back()->with('success', 'Contract marked as Finished.');
    }

    private function validateContract(Request $request, bool $isCreate): array
    {
        $rules = [
            'name'       => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'currency'   => 'required|string|size:3',
            'notes'      => 'nullable|string',
            'services'   => 'required|array|min:1',
            'services.*.name'        => 'required|string|max:255',
            'services.*.amount'      => 'required|numeric|min:0',
            'services.*.description' => 'nullable|string',
            'services.*.start_date'  => 'nullable|date',
            'services.*.end_date'    => 'nullable|date',
            'services.*.milestones'  => 'nullable|array|max:5',
            'services.*.milestones.*.execution_percentage' => 'nullable|numeric|min:0|max:100',
            'services.*.milestones.*.amount'               => 'nullable|numeric|min:0',
            'services.*.milestones.*.start_date'           => 'nullable|date',
            'services.*.milestones.*.end_date'             => 'nullable|date',
            'services.*.milestones.*.collection_days'      => 'nullable|integer|min:0',
        ];

        if (!$isCreate) {
            $rules['services.*.id'] = 'nullable|integer';
        }

        $data = $request->validate($rules);

        foreach ($data['services'] as $i => $svc) {
            $milestones = $svc['milestones'] ?? [];
            $totalPct   = collect($milestones)->sum(fn ($m) => (float) ($m['execution_percentage'] ?? 0));
            if ($totalPct > 100.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "services.{$i}.milestones" => 'Total execution percentage cannot exceed 100%.',
                ]);
            }
        }

        return $data;
    }

    private function persistService(
        CustomerContract $contract,
        array $svc,
        int $i,
        array $data,
        PortfolioCompany $portfolioCompany
    ): ContractService {
        $contractService = ContractService::create([
            'customer_contract_id' => $contract->id,
            'name'                 => $svc['name'],
            'description'          => $svc['description'] ?? null,
            'amount'               => (float) $svc['amount'],
            'start_date'           => $svc['start_date'] ?? null,
            'end_date'             => $svc['end_date'] ?? null,
            'sort_order'           => $i,
        ]);

        $this->saveMilestones($contractService, $svc['milestones'] ?? [], (float) $svc['amount']);
        $this->createLinkedProject($contractService, $svc, $data, $portfolioCompany);

        return $contractService;
    }

    private function saveMilestones(ContractService $service, array $milestones, float $serviceAmount): void
    {
        foreach ($milestones as $idx => $m) {
            $pct = (float) ($m['execution_percentage'] ?? 0);
            if ($pct <= 0 && empty($m['start_date']) && empty($m['end_date'])) {
                continue;
            }

            ContractServiceMilestone::create([
                'contract_service_id'  => $service->id,
                'milestone_index'      => $idx + 1,
                'execution_percentage' => $pct,
                'amount'               => isset($m['amount']) ? (float) $m['amount'] : round($serviceAmount * $pct / 100, 2),
                'start_date'           => $m['start_date'] ?? null,
                'end_date'             => $m['end_date'] ?? null,
                'collection_days'      => (int) ($m['collection_days'] ?? 0),
            ]);
        }
    }

    private function createLinkedProject(
        ContractService $service,
        array $svc,
        array $data,
        PortfolioCompany $portfolioCompany
    ): void {
        DB::table('projects')->insert([
            'portfolio_company_id' => $portfolioCompany->id,
            'contract_service_id'  => $service->id,
            'created_by'           => Auth::id(),
            'name'                 => $svc['name'],
            'status'               => 'not_started',
            'start_date'           => $svc['start_date'] ?? $data['start_date'] ?? null,
            'end_date'             => $svc['end_date'] ?? $data['end_date'] ?? null,
            'currency'             => strtoupper($data['currency']),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    private function syncLinkedProject(
        ContractService $service,
        array $svc,
        array $data,
        PortfolioCompany $portfolioCompany
    ): void {
        $project = DB::table('projects')->where('contract_service_id', $service->id)->first();

        if ($project) {
            DB::table('projects')->where('id', $project->id)->update([
                'name'       => $svc['name'],
                'start_date' => $svc['start_date'] ?? $data['start_date'] ?? null,
                'end_date'   => $svc['end_date'] ?? $data['end_date'] ?? null,
                'currency'   => strtoupper($data['currency']),
                'updated_at' => now(),
            ]);
        } else {
            $this->createLinkedProject($service, $svc, $data, $portfolioCompany);
        }
    }

    private function existingServiceNames(int $organizationId): array
    {
        return ContractService::query()
            ->whereHas('contract', fn ($q) => $q->where('organization_id', $organizationId))
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

    private function formatContract(CustomerContract $c): array
    {
        return [
            'id'         => $c->id,
            'name'       => $c->name,
            'code'       => $c->code,
            'start_date' => $c->start_date?->format('Y-m-d'),
            'end_date'   => $c->end_date?->format('Y-m-d'),
            'amount'     => (float) $c->amount,
            'currency'   => $c->currency,
            'status'     => $c->status,
            'notes'      => $c->notes,
            'services'   => $c->services->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'description' => $s->description,
                'amount'      => (float) $s->amount,
                'start_date'  => $s->start_date?->format('Y-m-d'),
                'end_date'    => $s->end_date?->format('Y-m-d'),
                'sort_order'  => $s->sort_order,
                'milestones'  => $s->milestones->map(fn ($m) => [
                    'milestone_index'      => $m->milestone_index,
                    'execution_percentage' => (float) $m->execution_percentage,
                    'amount'               => (float) $m->amount,
                    'start_date'           => $m->start_date?->format('Y-m-d'),
                    'end_date'             => $m->end_date?->format('Y-m-d'),
                    'collection_days'      => (int) $m->collection_days,
                ])->values()->all(),
                'execution_total_pct' => (float) $s->milestones->sum('execution_percentage'),
            ])->values()->all(),
        ];
    }
}
