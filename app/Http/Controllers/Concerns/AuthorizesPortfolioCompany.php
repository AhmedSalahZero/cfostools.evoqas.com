<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PortfolioCompany;

trait AuthorizesPortfolioCompany
{
    protected function authorizeCompany(
        PortfolioCompany|int $company,
        ?string $permission = null
    ): PortfolioCompany {
        if (is_int($company)) {
            $company = PortfolioCompany::findOrFail($company);
        }

        abort_unless(
            auth()->user()->canAccessPortfolioCompany($company, $permission),
            403,
            'You do not have access to this company.'
        );

        return $company;
    }

    protected function authorizeCompanyManage(PortfolioCompany $company): void
    {
        abort_unless(
            auth()->user()->canManagePortfolioCompany($company),
            403,
            'You do not have permission to manage this company.'
        );
    }
}
