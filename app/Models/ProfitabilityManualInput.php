<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitabilityManualInput extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'period_type',
        'period_label',
        'da_amount',
        'interest_amount',
        'tax_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'da_amount'       => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }
}