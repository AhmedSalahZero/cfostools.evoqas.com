<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\PortfolioCompany;
use App\Models\UserCompanyAssignment;
use App\Models\UserCompanyPermission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    const PERMISSIONS = [
        'view_company'           => 'View Company Page',
        'view_dashboard'         => 'View Dashboard',
        'sales_analysis'         => 'Sales Analysis',
        'export_sales_analysis'  => 'Export Sales Analysis',
        'expense_analysis'       => 'Expense Analysis',
        'profitability'          => 'Profitability Analysis',
        'financial_statements'   => 'Financial Statements',
        'budget_variance'        => 'Budget & Variance',
        'cash_forecast'          => 'Cash Flow Forecast',
        'kpi_tracking'           => 'KPI Tracking',
        'financial_studies'      => 'Financial Studies',
        'financial_planning'     => 'Financial Planning',
        'financial_model_studio' => 'Financial Model Studio',
        'documents'              => 'Documents',
        'contracts'              => 'Contracts',
        'surveys'                => 'Surveys',
        'statistica'             => 'Statistica',
        'projects'               => 'Projects & Tasks',
    ];

    public function index()
    {
        $authUser = Auth::user();

        $query = User::with(['roles', 'assignedCompanies'])
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['super-admin']);
            });

        if (!$authUser->hasRole('super-admin')) {
            $orgId = (int) $authUser->organization_id;

            $query->where(function ($builder) use ($orgId) {
                $builder->where('organization_id', $orgId)
                    ->orWhereHas('assignedCompanies', function ($companyQuery) use ($orgId) {
                        $companyQuery->where('organization_id', $orgId);
                    });
            });
        }

        $users = $query->get()->map(function ($user) {
            // Get roles per company from assignments
            $companyRoles = UserCompanyAssignment::where('user_id', $user->id)
                ->with('portfolioCompany')
                ->get()
                ->map(fn($a) => [
                    'company_name' => $a->portfolioCompany?->name,
                    'role'         => $a->role,
                ]);

            return [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'company_roles' => $companyRoles,
                'company_count' => $user->assignedCompanies->count(),
                'created_at'    => $user->created_at?->format('Y-m-d'),
            ];
        });

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    public function create()
    {
        $authUser = Auth::user();

        $companiesQuery = $authUser->hasRole('super-admin')
            ? PortfolioCompany::query()
            : PortfolioCompany::where('organization_id', $authUser->organization_id);

        $companies = $companiesQuery->orderBy('name')->get(['id', 'name', 'sector']);

        return Inertia::render('Users/Create', [
            'companies'   => $companies,
            'permissions' => self::PERMISSIONS,
        ]);
    }

    public function store(Request $request)
    {
        $authUser = Auth::user();

        $validated = $request->validate([
            'name'                          => ['required', 'string', 'max:255'],
            'email'                         => ['required', 'email', 'unique:users,email'],
            'password'                      => ['required', 'string', 'min:8'],
            'assigned_companies'            => ['nullable', 'array'],
            'assigned_companies.*.id'       => ['required', 'exists:portfolio_companies,id'],
            'assigned_companies.*.role'     => ['required', Rule::in(['manager', 'analyst', 'viewer'])],
            'assigned_companies.*.permissions' => ['nullable', 'array'],
            'assigned_companies.*.permissions.*' => ['string', Rule::in(array_keys(self::PERMISSIONS))],
        ]);

        $assignedCompanies = $this->validatedCompanies($validated['assigned_companies'] ?? []);
        $organizationId = $this->resolveOrganizationIdForUser($authUser, $assignedCompanies);

        // Create user — no global role anymore
        $user = User::create([
            'organization_id' => $organizationId,
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
        ]);

        // Assign companies with role + permissions per company
        foreach ($validated['assigned_companies'] ?? [] as $item) {
            UserCompanyAssignment::create([
                'user_id'              => $user->id,
                'portfolio_company_id' => $item['id'],
                'role'                 => $item['role'],
            ]);

            foreach ($item['permissions'] ?? [] as $perm) {
                UserCompanyPermission::create([
                    'user_id'              => $user->id,
                    'portfolio_company_id' => $item['id'],
                    'permission'           => $perm,
                ]);
            }
        }

        return redirect()->route('users.index')
            ->with('flash', ['success' => $user->name . ' has been created successfully.']);
    }

    public function edit(User $user)
    {
        $authUser = Auth::user();
        $this->authorizeManagedUser($authUser, $user);

        $companiesQuery = $authUser->hasRole('super-admin')
            ? PortfolioCompany::query()
            : PortfolioCompany::where('organization_id', $authUser->organization_id);

        $companies = $companiesQuery->orderBy('name')->get(['id', 'name', 'sector']);

        // Get current assignments with role
        $assignments = UserCompanyAssignment::where('user_id', $user->id)->get();

        // Get current permissions grouped by company_id
        $currentPermissions = UserCompanyPermission::where('user_id', $user->id)
            ->get()
            ->groupBy('portfolio_company_id')
            ->map(fn($perms) => $perms->pluck('permission')->toArray());

        // Build assigned companies array with role + permissions
        $assignedCompanies = $assignments->map(fn($a) => [
            'id'          => $a->portfolio_company_id,
            'role'        => $a->role,
            'permissions' => $currentPermissions[$a->portfolio_company_id] ?? [],
        ])->values();

        return Inertia::render('Users/Edit', [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'companies'         => $companies,
            'permissions'       => self::PERMISSIONS,
            'assignedCompanies' => $assignedCompanies,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $authUser = Auth::user();
        $this->authorizeManagedUser($authUser, $user);

        $validated = $request->validate([
            'name'                             => ['required', 'string', 'max:255'],
            'email'                            => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'                         => ['nullable', 'string', 'min:8'],
            'assigned_companies'               => ['nullable', 'array'],
            'assigned_companies.*.id'          => ['required', 'exists:portfolio_companies,id'],
            'assigned_companies.*.role'        => ['required', Rule::in(['manager', 'analyst', 'viewer'])],
            'assigned_companies.*.permissions' => ['nullable', 'array'],
            'assigned_companies.*.permissions.*' => ['string', Rule::in(array_keys(self::PERMISSIONS))],
        ]);

        $assignedCompanies = $this->validatedCompanies($validated['assigned_companies'] ?? []);
        $organizationId = $this->resolveOrganizationIdForUser($authUser, $assignedCompanies);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        $user->organization_id = $organizationId;
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        // Re-sync assignments
        UserCompanyAssignment::where('user_id', $user->id)->delete();
        UserCompanyPermission::where('user_id', $user->id)->delete();

        foreach ($validated['assigned_companies'] ?? [] as $item) {
            UserCompanyAssignment::create([
                'user_id'              => $user->id,
                'portfolio_company_id' => $item['id'],
                'role'                 => $item['role'],
            ]);

            foreach ($item['permissions'] ?? [] as $perm) {
                UserCompanyPermission::create([
                    'user_id'              => $user->id,
                    'portfolio_company_id' => $item['id'],
                    'permission'           => $perm,
                ]);
            }
        }

        return redirect()->route('users.index')
            ->with('flash', ['success' => $user->name . ' has been updated successfully.']);
    }

    public function destroy(User $user)
    {
        $this->authorizeManagedUser(Auth::user(), $user);

        UserCompanyAssignment::where('user_id', $user->id)->delete();
        UserCompanyPermission::where('user_id', $user->id)->delete();
        $user->delete();

        return redirect()->route('users.index')
            ->with('flash', ['success' => 'User deleted successfully.']);
    }

    private function validatedCompanies(array $assignedCompanies): Collection
    {
        if (empty($assignedCompanies)) {
            return collect();
        }

        return PortfolioCompany::query()
            ->whereIn('id', collect($assignedCompanies)->pluck('id')->all())
            ->get(['id', 'organization_id']);
    }

    private function resolveOrganizationIdForUser(User $authUser, Collection $assignedCompanies): ?int
    {
        if (!$authUser->hasRole('super-admin')) {
            $this->ensureCompaniesBelongToOrganization($assignedCompanies, (int) $authUser->organization_id);
            return (int) $authUser->organization_id;
        }

        if ($assignedCompanies->isEmpty()) {
            return $authUser->organization_id ? (int) $authUser->organization_id : null;
        }

        $orgIds = $assignedCompanies->pluck('organization_id')->unique()->values();
        abort_if($orgIds->count() !== 1, 422, 'Assigned companies must belong to the same organization.');

        return (int) $orgIds->first();
    }

    private function ensureCompaniesBelongToOrganization(Collection $assignedCompanies, int $organizationId): void
    {
        abort_if(
            $assignedCompanies->contains(fn ($company) => (int) $company->organization_id !== $organizationId),
            403,
            'You cannot manage users for another organization.'
        );
    }

    private function authorizeManagedUser(User $authUser, User $user): void
    {
        if ($authUser->hasRole('super-admin')) {
            return;
        }

        abort_if($user->hasRole('super-admin'), 403, 'You cannot manage this user.');

        $organizationId = (int) $authUser->organization_id;
        $assignedCompanyIds = $user->assignedCompanies()->pluck('portfolio_companies.organization_id');
        $hasAssignedCompanyInOrg = $assignedCompanyIds->contains(fn ($orgId) => (int) $orgId === $organizationId);

        abort_unless(
            (int) $user->organization_id === $organizationId || $hasAssignedCompanyInOrg,
            403,
            'You cannot manage this user.'
        );
    }
}