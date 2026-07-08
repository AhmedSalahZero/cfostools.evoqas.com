<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashForecastEntry extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'financial_statement_id',
        'type',
        'category',
        'description',
        'amount',
        'month',
        'is_recurring',
        'recurring_end_month',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'float',
        'is_recurring' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function statement()
    {
        return $this->belongsTo(FinancialStatement::class, 'financial_statement_id');
    }

    /**
     * Expand recurring entries into individual month entries.
     * Returns array of ['month' => 'YYYY-MM', 'amount' => float] for each active month.
     */
    public function expandedMonths(): array
    {
        if (!$this->is_recurring || !$this->recurring_end_month) {
            return [['month' => $this->month, 'amount' => $this->amount]];
        }

        $months = [];
        $current = \Carbon\Carbon::parse($this->month . '-01');
        $end     = \Carbon\Carbon::parse($this->recurring_end_month . '-01');

        while ($current->lte($end)) {
            $months[] = ['month' => $current->format('Y-m'), 'amount' => $this->amount];
            $current->addMonth();
        }

        return $months;
    }
}