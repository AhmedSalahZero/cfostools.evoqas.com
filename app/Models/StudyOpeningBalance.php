<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyOpeningBalance extends Model
{
    protected $table = 'study_opening_balances';

    protected $fillable = [
        'financial_study_id',
        'as_of_date',
        'notes',
        'is_balanced',

        // Dedicated scalar fields
        'cash_bank',
        'paid_up_capital',
        'legal_reserve',
        'retained_earnings',

        // JSON columns
        'fixed_assets',
        'inventory',
        'current_assets',
        'other_non_current',
        'long_term_liabilities',
        'current_liabilities',
        'equity',

        // Computed decimal totals
        'total_gross_fa',
        'total_accum_dep',
        'total_net_fa',
        'total_inventory',
        'total_current_assets',
        'total_other_non_current',
        'total_long_term_liabilities',
        'total_current_liabilities',
        'total_equity',
        'total_assets',
        'total_liabilities',
    ];

    protected $casts = [
        'as_of_date'  => 'date',
        'is_balanced' => 'boolean',

        // Scalar fields
        'cash_bank'         => 'decimal:2',
        'paid_up_capital'   => 'decimal:2',
        'legal_reserve'     => 'decimal:2',
        'retained_earnings' => 'decimal:2',

        // Auto-decode JSON on read, auto-encode on write
        'fixed_assets'           => 'array',
        'inventory'              => 'array',
        'current_assets'         => 'array',
        'other_non_current'      => 'array',
        'long_term_liabilities'  => 'array',
        'current_liabilities'    => 'array',
        'equity'                 => 'array',

        // Decimal totals
        'total_gross_fa'                 => 'decimal:2',
        'total_accum_dep'                => 'decimal:2',
        'total_net_fa'                   => 'decimal:2',
        'total_inventory'                => 'decimal:2',
        'total_current_assets'           => 'decimal:2',
        'total_other_non_current'        => 'decimal:2',
        'total_long_term_liabilities'    => 'decimal:2',
        'total_current_liabilities'      => 'decimal:2',
        'total_equity'                   => 'decimal:2',
        'total_assets'                   => 'decimal:2',
        'total_liabilities'              => 'decimal:2',
    ];

    // ── Relationship ───────────────────────────────────────────────────────
    public function financialStudy(): BelongsTo
    {
        return $this->belongsTo(FinancialStudy::class, 'financial_study_id');
    }

    // ── Helper: compute all totals from the raw JSON arrays ───────────────
    //  Call this before saving to keep decimal columns in sync.
    public function computeTotals(): void
    {
        $sumArr = fn(array $rows, string $field = 'amount') =>
            collect($rows)->sum(fn($r) => (float) ($r[$field] ?? 0));

        $fixedAssets = $this->fixed_assets ?? [];
        $this->total_gross_fa  = collect($fixedAssets)->sum(fn($fa) => (float)($fa['gross_amount'] ?? 0));
        $this->total_accum_dep = collect($fixedAssets)->sum(fn($fa) => (float)($fa['accum_dep']    ?? 0));
        $this->total_net_fa    = $this->total_gross_fa - $this->total_accum_dep;

        $this->total_inventory              = $sumArr($this->inventory              ?? []);
        $this->total_current_assets         = $sumArr($this->current_assets         ?? []);
        $this->total_other_non_current      = $sumArr($this->other_non_current      ?? []);
        $this->total_long_term_liabilities  = $sumArr($this->long_term_liabilities  ?? []);
        $this->total_current_liabilities    = $sumArr($this->current_liabilities    ?? []);
        $this->total_equity                 = $sumArr($this->equity                 ?? []);

        $this->total_assets      = $this->total_net_fa
                                 + $this->total_other_non_current
                                 + $this->total_inventory
                                 + $this->total_current_assets;

        $this->total_liabilities = $this->total_long_term_liabilities
                                 + $this->total_current_liabilities;

        $this->is_balanced = abs($this->total_assets - ($this->total_liabilities + $this->total_equity)) < 1;
    }

    // ── Accessor: net FA (in case you need it on the fly) ────────────────
    public function getNetFaAttribute(): float
    {
        return (float)$this->total_gross_fa - (float)$this->total_accum_dep;
    }
}