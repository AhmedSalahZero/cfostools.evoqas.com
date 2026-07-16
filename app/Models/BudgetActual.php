<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetActual extends Model
{
    protected $fillable = [
        'budget_line_item_id',
        'monthly_actuals',
        'source',
        'source_statement_id',
        'entered_by',
    ];

    protected $casts = [
        'monthly_actuals' => 'array',
    ];

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(BudgetLineItem::class, 'budget_line_item_id');
    }

    public function sourceStatement(): BelongsTo
    {
        return $this->belongsTo(FinancialStatement::class, 'source_statement_id');
    }

    // Helper: sum of all 12 months actual
    public function annualActual(): float
    {
        if (!$this->monthly_actuals) return 0;
        return array_sum($this->monthly_actuals);
    }
}