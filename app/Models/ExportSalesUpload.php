<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportSalesUpload extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'file_path',
        'original_filename',
        'period_from',
        'period_to',
        'date_format',
        'status',
        'row_count',
        'error_message',
        'uploaded_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
    ];

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}