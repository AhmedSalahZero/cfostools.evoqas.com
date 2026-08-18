<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'data_room_subsection_id',
        'name',
        'path',
        'mime_type',
        'category',
        'uploaded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Category options ───────────────────────────────────────────────────────
    const CATEGORIES = [
        'due_diligence'       => 'Due Diligence',
        'contracts_legal'     => 'Contracts & Legal',
        'financial_documents' => 'Financial Documents',
        'corporate_documents' => 'Corporate Documents',
        'operational'         => 'Operational',
        'other'               => 'Other',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function subsection(): BelongsTo
    {
        return $this->belongsTo(DataRoomSubsection::class, 'data_room_subsection_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    /**
     * Human-readable category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Simplified file type for display (PDF, Excel, Word, etc.)
     */
    public function getFileTypeAttribute(): string
    {
        $mime = $this->mime_type ?? '';

        if (str_contains($mime, 'pdf'))                          return 'PDF';
        if (str_contains($mime, 'sheet') ||
            str_contains($mime, 'excel'))                        return 'Excel';
        if ($mime === 'text/csv' || $mime === 'text/plain')      return 'CSV';
        if (str_contains($mime, 'wordprocessingml') ||
            str_contains($mime, 'msword'))                       return 'Word';
        if (str_contains($mime, 'presentationml') ||
            str_contains($mime, 'powerpoint'))                   return 'PowerPoint';
        if (str_contains($mime, 'image'))                        return 'Image';

        return 'File';
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /**
     * Filter by section/category.
     * Usage: Document::forCompany($id)->inSection('due_diligence')->get()
     */
    public function scopeInSection($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Filter by portfolio company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('portfolio_company_id', $companyId);
    }
}