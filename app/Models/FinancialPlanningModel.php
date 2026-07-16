<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPlanningModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_company_id',
        'uploaded_by',
        'name',
        'model_type',        // 'complex' or 'simple'
        'original_filename',
        'file_path',
        'version',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}