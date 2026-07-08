<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesFieldMapping extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'field_key',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }
}