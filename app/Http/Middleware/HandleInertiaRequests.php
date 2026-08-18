<?php

namespace App\Http\Middleware;

use App\Services\ContractAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
        $myTaskAlerts = ['count' => 0];

        if (
            $user
            && ($user->hasRole('super-admin') || $user->hasRole('admin'))
            && Schema::hasTable('customer_contracts')
            && Schema::hasColumns('customer_contracts', ['organization_id', 'status', 'end_date'])
        ) {
            $orgId = (int) $user->organization_id;
            $items = app(ContractAlertService::class)->expiredUnfinished($orgId);
            $contractAlerts = [
                'count' => $items->count(),
                'items' => $items->values()->all(),
            ];
        }

        if ($user && Schema::hasTable('project_task_assignees')) {
            $query = DB::table('project_task_assignees')
                ->where('user_id', $user->id);

            if (Schema::hasColumn('project_task_assignees', 'seen_at')) {
                $query->whereNull('seen_at');
            } else {
                $query->whereRaw('1 = 0');
            }

            $myTaskAlerts['count'] = $query->count();
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
            'myTaskAlerts' => $myTaskAlerts,
        ];
    }
}
