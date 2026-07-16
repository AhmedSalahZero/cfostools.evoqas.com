<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetSection extends Model
{
    protected $fillable = [
        'budget_statement_id',
        'statement_type',
        'section_key',
        'display_name',
        'is_computed',
        'computed_from',
        'sort_order',
    ];

    protected $casts = [
        'is_computed'   => 'boolean',
        'computed_from' => 'array',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BudgetStatement::class, 'budget_statement_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(BudgetGroup::class)->orderBy('sort_order');
    }
}