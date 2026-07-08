<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReport extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'name',
        'report_type',
        'config',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'config'    => 'array',
        'is_system' => 'boolean',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
