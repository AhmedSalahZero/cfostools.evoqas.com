<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparisonDashboardNote extends Model
{
    protected $fillable = [
        'comparison_dashboard_id',
        'section_key',
        'note',
        'updated_by',
    ];

    public function dashboard()
    {
        return $this->belongsTo(ComparisonDashboard::class, 'comparison_dashboard_id');
    }
}
