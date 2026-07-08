<?php

namespace App\Services;

use App\Models\CustomerContract;
use App\Models\PortfolioCompany;

class ContractCodeGenerator
{
    public function generate(PortfolioCompany $company): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $company->name) ?? '';
        $prefix = strtoupper(substr($letters, 0, 2));
        if (strlen($prefix) < 2) {
            $prefix = str_pad($prefix, 2, 'X');
        }

        $year  = now()->format('Y');
        $month = now()->format('m');
        $pattern = "{$prefix}/{$year}/{$month}/%";

        $lastCode = CustomerContract::where('portfolio_company_id', $company->id)
            ->where('code', 'like', $pattern)
            ->orderByDesc('code')
            ->value('code');

        $seq = 1;
        if ($lastCode && preg_match('/(\d{4})$/', $lastCode, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('%s/%s/%s/%04d', $prefix, $year, $month, $seq);
    }
}
