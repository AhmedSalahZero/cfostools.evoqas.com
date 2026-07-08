<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialStatement extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'period_from',
        'period_to',
        'currency',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function sections()
    {
        return $this->hasMany(FsSection::class);
    }

    public function ratios()
    {
        return $this->hasMany(FsRatio::class);
    }
}