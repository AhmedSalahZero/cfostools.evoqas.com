<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ComparisonDashboard extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'name',
        'periods',
        'share_token',
        'is_public',
        'created_by',
    ];

    protected $casts = [
        'periods'   => 'array',
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ComparisonDashboard $dashboard) {
            if (empty($dashboard->share_token)) {
                $dashboard->share_token = Str::random(32);
            }
        });
    }

    public function notes()
    {
        return $this->hasMany(ComparisonDashboardNote::class);
    }
}
