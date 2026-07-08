<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BudgetLineItem extends Model
{
    protected $fillable = [
        'budget_group_id',
        'label',
        'monthly_amounts',
        'sort_order',
    ];

    protected $casts = [
        'monthly_amounts' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(BudgetGroup::class, 'budget_group_id');
    }

    public function actual(): HasOne
    {
        return $this->hasOne(BudgetActual::class);
    }

    // Helper: sum of all 12 months budget
    public function annualBudget(): float
    {
        if (!$this->monthly_amounts) return 0;
        return array_sum($this->monthly_amounts);
    }
}