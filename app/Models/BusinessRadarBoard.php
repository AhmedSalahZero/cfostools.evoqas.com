<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRadarBoard extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'name',
        'description',
        'created_by',
    ];

    public function portfolioCompany()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(BusinessRadarItem::class);
    }

    public function challenges()
    {
        return $this->items()->where('type', 'challenge');
    }

    public function potentials()
    {
        return $this->items()->where('type', 'potential');
    }
}
