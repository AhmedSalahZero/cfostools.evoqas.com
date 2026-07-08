<?php

namespace App\Services;

use App\Models\CustomerContract;
use Illuminate\Support\Collection;

class ContractAlertService
{
    /**
     * Contracts past end_date that are not marked finished.
     */
    public function expiredUnfinished(int $organizationId): Collection
    {
        return CustomerContract::where('organization_id', $organizationId)
            ->whereIn('status', ['draft', 'running'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->with('portfolioCompany')
            ->orderBy('end_date')
            ->get()
            ->map(fn (CustomerContract $c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'code'          => $c->code,
                'end_date'      => $c->end_date?->format('Y-m-d'),
                'status'        => $c->status,
                'customer_id'   => $c->portfolio_company_id,
                'customer_name' => $c->portfolioCompany?->name ?? '—',
            ]);
    }
}
