<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'legal_structure',
        'logo',
        'base_currency',
    ];

    /**
     * Get the users that belong to the organization.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the portfolio companies that belong to the organization.
     */
    public function portfolioCompanies()
    {
        return $this->hasMany(PortfolioCompany::class);
    }
}