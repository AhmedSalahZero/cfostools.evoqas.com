<?php

namespace App\Http\Middleware;

use App\Services\ContractAlertService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $contractAlerts = ['count' => 0, 'items' => []];

        if ($user && ($user->hasRole('super-admin') || $user->hasRole('admin'))) {
            $orgId = (int) $user->organization_id;
            $items = app(ContractAlertService::class)->expiredUnfinished($orgId);
            $contractAlerts = [
                'count' => $items->count(),
                'items' => $items->values()->all(),
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->toArray(),
                    'can_manage_portfolio_companies' => $user->canManagePortfolioCompanies(),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('flash.success'),
            ],
            'contractAlerts' => $contractAlerts,
        ];
    }
}
