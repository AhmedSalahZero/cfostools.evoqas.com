<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class PortfolioCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',                 // ← NEW: 'investment' or 'prospect'
        'name',
        'lead_source',
        'sector',
        'status',
        'transaction_date',
        'invested_amount',
        'invested_currency',
        'fx_currency',
        'fx_rate',
        'equity_stake',
        'ebitda_multiplier',
        'entry_valuation',
        'current_valuation',
        'moic',
        'irr',
        'last_financial_update',
        'notes',
    ];

    protected $casts = [
        'transaction_date'      => 'date',
        'last_financial_update' => 'date',
        'invested_amount'       => 'decimal:2',
        'fx_rate'               => 'decimal:6',
        'equity_stake'          => 'decimal:4',
        'ebitda_multiplier'     => 'decimal:2',
        'entry_valuation'       => 'decimal:2',
        'current_valuation'     => 'decimal:2',
        'moic'                  => 'decimal:2',
        'irr'                   => 'decimal:2',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function kpiTrackings()
    {
        return $this->hasMany(KpiTracking::class);
    }

    public function contracts()
    {
        return $this->hasMany(CustomerContract::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'on_track' => 'green',
            'at_risk'  => 'red',
            'watch'    => 'yellow',
            default    => 'gray',
        };
    }

    public function getEquityStakePercentAttribute(): float
    {
        return round($this->equity_stake * 100, 2);
    }

    public function financialStudies()
    {
    return $this->hasMany(FinancialStudy::class);
    }

}