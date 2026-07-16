<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitabilityPlMapping extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'expense_category',
        'pl_line',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }
}