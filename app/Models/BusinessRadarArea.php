<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRadarArea extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function items()
    {
        return $this->belongsToMany(
            BusinessRadarItem::class,
            'business_radar_item_areas',
            'business_radar_area_id',
            'business_radar_item_id'
        )->withPivot('impact_score')->withTimestamps();
    }

    // Built-in areas (available to everyone)
    public function scopeBuiltIn($query)
    {
        return $query->whereNull('organization_id');
    }

    // Built-in areas + this organization's own custom areas
    public function scopeForOrg($query, $orgId)
    {
        return $query->where(function ($q) use ($orgId) {
            $q->whereNull('organization_id')
              ->orWhere('organization_id', $orgId);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
