<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerContract extends Model
{
    protected $fillable = [
        'organization_id',
        'portfolio_company_id',
        'name',
        'code',
        'start_date',
        'end_date',
        'amount',
        'currency',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'amount'     => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function portfolioCompany(): BelongsTo
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ContractService::class)->orderBy('sort_order')->orderBy('id');
    }
}
