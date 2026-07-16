<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelStudioWorkbook extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'created_by',
        'name',
        'sheets_data',
        'charts_data',
        'last_saved_at',
    ];

    protected $casts = [
        'sheets_data' => 'array',
        'charts_data' => 'array',
        'last_saved_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}