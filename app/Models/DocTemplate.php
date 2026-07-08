<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocTemplate extends Model
{
    protected $table = 'doc_templates';

    protected $fillable = [
        'slug',
        'name',
        'short_name',
        'category',
        'icon',
        'description',
        'variables',
        'sort_order',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function documents(): HasMany
    {
        return $this->hasMany(Investadoc::class, 'doc_template_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'pre_loi'       => 'Pre-LOI',
            'due_diligence' => 'Due Diligence',
            'closing'       => 'Closing',
            default         => ucfirst($this->category),
        };
    }
}