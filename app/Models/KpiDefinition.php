<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDefinition extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'category',
        'unit',
        'source',
        'fs_mapping',
        'higher_is_better',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'higher_is_better' => 'boolean',
        'is_active'        => 'boolean',
    ];

    // Belongs to an org (null = system standard)
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // All tracking entries for this KPI
    public function trackings()
    {
        return $this->hasMany(KpiTracking::class);
    }

    // Scope: system-wide standard KPIs
    public function scopeStandard($query)
    {
        return $query->whereNull('organization_id');
    }

    // Scope: custom KPIs for a specific org
    public function scopeForOrg($query, $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // Scope: active only
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function companyConfigs()
     {
            return $this->hasMany(CompanyKpiConfig::class, 'kpi_definition_id');
     }
}