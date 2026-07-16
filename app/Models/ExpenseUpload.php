<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseUpload extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'uploaded_by',
        'file_path',
        'period_from',
        'period_to',
        'date_format',
        'row_count',
        'status',
        'error_message',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function expenseData()
    {
        return $this->hasMany(ExpenseData::class, 'upload_id');
    }
}