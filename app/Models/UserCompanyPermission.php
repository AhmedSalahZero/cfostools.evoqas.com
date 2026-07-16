<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCompanyPermission extends Model
{
    protected $fillable = [
        'user_id',
        'portfolio_company_id',
        'permission',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }
}