<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investadoc extends Model
{
    protected $table = 'investadocs';

    protected $fillable = [
        'organization_id',
        'doc_template_id',
        'created_by',
        'portfolio_company_id',
        'title',
        'target_company_name',
        'status',
        'variables_data',
        'file_path',
        'sent_at',
        'signed_at',
        'notes',
    ];

    protected $casts = [
        'variables_data' => 'array',
        'sent_at'        => 'datetime',
        'signed_at'      => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocTemplate::class, 'doc_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function portfolioCompany(): BelongsTo
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function isLinkedToCompany(): bool
    {
        return !is_null($this->portfolio_company_id);
    }
}