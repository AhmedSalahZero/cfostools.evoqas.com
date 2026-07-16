<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FsSection extends Model
{
    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'is_computed' => 'boolean',
    ];

    public function statement()
    {
        return $this->belongsTo(FinancialStatement::class, 'financial_statement_id');
    }

    public function lineItems()
    {
        return $this->hasMany(FsLineItem::class, 'fs_section_id')->orderBy('sort_order');
    }
}