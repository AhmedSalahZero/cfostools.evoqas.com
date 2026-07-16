<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseData extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'upload_id',
        'date',
        'expense_category',
        'expense_sub_category',
        'expense_name',
        'expense_amount',
    ];

    protected $casts = [
        'date'           => 'date',
        'expense_amount' => 'decimal:2',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    public function upload()
    {
        return $this->belongsTo(ExpenseUpload::class, 'upload_id');
    }
}