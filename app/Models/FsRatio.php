<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FsRatio extends Model
{
    protected $fillable = [
        'financial_statement_id',
        'ratio_group',
        'ratio_key',
        'ratio_label',
        'ratio_value',
    ];

    protected $casts = [
        'ratio_value' => 'decimal:6',
    ];

    public function statement()
    {
        return $this->belongsTo(FinancialStatement::class, 'financial_statement_id');
    }
}