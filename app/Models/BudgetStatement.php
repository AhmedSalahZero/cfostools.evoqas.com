<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetStatement extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'name',
        'year',
        'currency',
        'status',
        'notes',
        'created_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(BudgetSection::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}