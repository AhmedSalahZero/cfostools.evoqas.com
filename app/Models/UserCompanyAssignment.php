<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCompanyAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'portfolio_company_id',
        'role',
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