<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Organization;
use App\Models\PortfolioCompany;
use App\Models\User;
use App\Services\PortfolioCompanyPurger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;


class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::withCount('users')
            ->orderBy('name')
            ->get()
            ->map(function ($org) {
                return [
                    'id'               => $org->id,
                    'name'             => $org->name,
                    'legal_structure'  => $org->legal_structure,
                    'base_currency'    => $org->base_currency,
                    'logo'             => $org->logo,
                    'user_count'       => $org->users_count,
                    'created_at'       => $org->created_at?->format('Y-m-d'),
                ];
            });

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create()
    {
        return Inertia::render('Organizations/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'legal_structure' => ['required', 'string', 'max:100'],
            'base_currency'   => ['required', 'string', 'size:3'],
            'admin_name'      => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'password'        => ['required', 'string', 'min:8'],
            'logo'            => ['nullable', 'image', 'max:2048'],
        ]);

        // Handle logo upload
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('organization-logos', 'public');
        }

        // Create organization
        $organization = Organization::create([
            'name'            => $validated['name'],
            'legal_structure' => $validated['legal_structure'],
            'base_currency'   => $validated['base_currency'],
            'logo'            => $logoPath,
        ]);

        // Create admin user
        $user = User::create([
            'organization_id' => $organization->id,
            'name'            => $validated['admin_name'],
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
        ]);

        $user->assignRole('admin');

        return redirect()->route('organizations.index')
            ->with('flash', ['success' => $organization->name . ' has been created successfully.']);
    }

    public function edit(Organization $organization)
    {
        // Get the first admin user of this organization
        $adminUser = User::where('organization_id', $organization->id)
            ->first();

        return Inertia::render('Organizations/Edit', [
            'organization' => [
                'id'              => $organization->id,
                'name'            => $organization->name,
                'legal_structure' => $organization->legal_structure,
                'base_currency'   => $organization->base_currency,
                'logo'            => $organization->logo,
            ],
            'adminUser' => $adminUser ? [
                'id'    => $adminUser->id,
                'name'  => $adminUser->name,
                'email' => $adminUser->email,
            ] : null,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        // Get the admin user to validate unique email excluding themselves
        $adminUser = User::where('organization_id', $organization->id)->first();

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'legal_structure' => ['required', 'string', 'max:100'],
            'base_currency'   => ['required', 'string', 'size:3'],
            'admin_name'      => ['required', 'string', 'max:255'],
            'email'           => [
                'required',
                'email',
                // Exclude the current admin user's email from the unique check
                Rule::unique('users', 'email')->ignore($adminUser?->id),
            ],
            'password'        => ['nullable', 'string', 'min:8'],
            'logo'            => ['nullable', 'image', 'max:2048'],
        ]);

        // Handle logo upload — only update if new file provided
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($organization->logo) {
                Storage::disk('public')->delete($organization->logo);
            }
            $organization->logo = $request->file('logo')->store('organization-logos', 'public');
        }

        // Update organization
        $organization->name            = $validated['name'];
        $organization->legal_structure = $validated['legal_structure'];
        $organization->base_currency   = $validated['base_currency'];
        $organization->save();

        // Update admin user
        if ($adminUser) {
            $adminUser->name  = $validated['admin_name'];
            $adminUser->email = $validated['email'];

            // Only update password if a new one was provided
            if (!empty($validated['password'])) {
                $adminUser->password = Hash::make($validated['password']);
            }

            $adminUser->save();
        }

        return redirect()->route('organizations.index')
            ->with('flash', ['success' => $organization->name . ' has been updated successfully.']);
    }

   public function destroy(Organization $organization, PortfolioCompanyPurger $purger)
{
    // Delete logo file if exists
    if ($organization->logo) {
        Storage::disk('public')->delete($organization->logo);
    }

    // Purge each company explicitly. The database would cascade the rows away
    // on its own, but only the purger also removes their uploaded files.
    PortfolioCompany::where('organization_id', $organization->id)
        ->get()
        ->each(fn (PortfolioCompany $company) => $purger->purge($company));

    // Delete all users belonging to this organization BEFORE deleting the org
    // This prevents them becoming orphaned with null organization_id
   User::where('organization_id', $organization->id)
    ->whereDoesntHave('roles', function ($query) {
        $query->where('name', 'super-admin');
    })
    ->delete();

    // Now delete the organization
    $organization->delete();

    return redirect()->route('organizations.index')
        ->with('flash', ['success' => 'Organization deleted successfully.']);
}


}