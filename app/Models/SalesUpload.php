<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesUpload extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'uploaded_by',
        'file_path',
        'period_from',
        'period_to',
        'row_count',
        'status',
        'error_message',
        'date_format',
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

    public function salesData()
    {
        return $this->hasMany(SalesData::class, 'upload_id');
    }
}