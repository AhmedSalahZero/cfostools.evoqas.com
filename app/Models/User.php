<?php

namespace App\Models;

// These lines tell PHP where to find ready-made tools (traits) we want to use
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\UserCompanyPermission;
use App\Models\PortfolioCompany;
use App\Models\UserCompanyAssignment;

// ────────────────────────────────────────────────
// NEW LINE → We are telling Laravel: "Please give this User permission/roles abilities"
// This line must come before the class starts
use Spatie\Permission\Traits\HasRoles;
// ────────────────────────────────────────────────

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // ────────────────────────────────────────────────
    // NEW LINE → We are attaching ("using") the roles system to every User object
    // After this line, you can write things like: $user->hasRole('admin')
    use HasRoles;
    // ────────────────────────────────────────────────

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

// Companies assigned to this user
    public function assignedCompanies()
    {
        return $this->belongsToMany(
            PortfolioCompany::class,
            'user_company_assignments',
            'user_id',
            'portfolio_company_id'
        );
    }

    // Permissions this user has per company
    public function companyPermissions()
    {
        return $this->hasMany(UserCompanyPermission::class);
    }

    /**
     * Send the password reset notification.
     * Uses custom notification so the reset link is /reset-password?token=...&email=...
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function canManagePortfolioCompanies(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin']);
    }

    public function canEditPortfolioCompany(PortfolioCompany $company): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        if ($this->hasRole('admin') && (int) $this->organization_id === (int) $company->organization_id) {
            return true;
        }

        return UserCompanyAssignment::where('user_id', $this->id)
            ->where('portfolio_company_id', $company->id)
            ->where('role', 'manager')
            ->exists();
    }

    public function canAccessPortfolioCompany(PortfolioCompany $company, ?string $permission = null): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        if ($this->hasRole('admin') && (int) $this->organization_id === (int) $company->organization_id) {
            return true;
        }

        $assignment = UserCompanyAssignment::where('user_id', $this->id)
            ->where('portfolio_company_id', $company->id)
            ->first();

        if (!$assignment) {
            return false;
        }

        if ($assignment->role === 'manager') {
            return true;
        }

        if ($permission === null) {
            return UserCompanyPermission::where('user_id', $this->id)
                ->where('portfolio_company_id', $company->id)
                ->exists();
        }

        return $this->hasCompanyPermission($company->id, $permission);
    }

    public function hasCompanyPermission(int $companyId, string $permission): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        if ($this->hasRole('admin')) {
            $company = PortfolioCompany::find($companyId);
            if ($company && (int) $this->organization_id === (int) $company->organization_id) {
                return true;
            }
        }

        if (UserCompanyAssignment::where('user_id', $this->id)
            ->where('portfolio_company_id', $companyId)
            ->where('role', 'manager')
            ->exists()) {
            return true;
        }

        return UserCompanyPermission::where('user_id', $this->id)
            ->where('portfolio_company_id', $companyId)
            ->where('permission', $permission)
            ->exists();
    }

    public function canManagePortfolioCompany(PortfolioCompany $company): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        return $this->hasRole('admin')
            && (int) $this->organization_id === (int) $company->organization_id;
    }
}