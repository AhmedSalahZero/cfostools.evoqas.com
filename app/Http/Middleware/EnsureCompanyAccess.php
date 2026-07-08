<?php

namespace App\Http\Middleware;

use App\Models\PortfolioCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAccess
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $param = $request->route('company')
            ?? $request->route('portfolioCompany')
            ?? $request->route('portfolio_company');

        if ($param === null) {
            return $next($request);
        }

        $company = $param instanceof PortfolioCompany
            ? $param
            : PortfolioCompany::findOrFail($param);

        abort_unless(
            $request->user()->canAccessPortfolioCompany($company, $permission),
            403,
            'You do not have access to this company.'
        );

        return $next($request);
    }
}
