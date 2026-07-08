<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetGroup extends Model
{
    protected $fillable = [
        'budget_section_id',
        'name',
        'sort_order',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(BudgetSection::class, 'budget_section_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(BudgetLineItem::class)->orderBy('sort_order');
    }
}