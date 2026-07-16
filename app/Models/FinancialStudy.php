<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialStudy extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_company_id',
        'name',
        'study_currency',
        'study_start_date',
        'duration_years',
        'study_end_date',
        'operation_start_date',
        'business_type',
        'business_sector',
        'corporate_tax_rate',
        'required_investment_return_pct',
        'perpetual_growth_rate_pct',
        'general_assumptions',
        'projections',
        'products',
        'comments',
    ];

    protected $casts = [
        'study_start_date' => 'date',
        'study_end_date'   => 'date',
        'operation_start_date' => 'date',
        'general_assumptions'  => 'array',
        'projections'          => 'array',
        'corporate_tax_rate'   => 'decimal:4',
        'required_investment_return_pct' => 'decimal:4',
        'perpetual_growth_rate_pct'      => 'decimal:4',
        'products' => 'array',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }
}