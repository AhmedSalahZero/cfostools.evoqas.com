<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiTracking extends Model
{
    protected $fillable = [
        'company_id',
        'kpi_definition_id',
        'period_type',
        'period_label',
        'target',
        'actual',
        'notes',
        'entered_by',
        'auto_synced',
    ];

    protected $casts = [
        'target'      => 'float',
        'actual'      => 'float',
        'auto_synced' => 'boolean',
    ];

    // The KPI definition this tracking belongs to
    public function definition()
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }

    // The portfolio company
    public function company()
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    // The user who entered it
    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    // Computed: variance from target (actual - target)
    public function getVarianceAttribute()
    {
        if (is_null($this->actual) || is_null($this->target)) return null;
        return $this->actual - $this->target;
    }

    // Computed: variance as percentage
    public function getVariancePercentAttribute()
    {
        if (is_null($this->actual) || is_null($this->target) || $this->target == 0) return null;
        return (($this->actual - $this->target) / abs($this->target)) * 100;
    }

    // Computed: traffic light status
    public function getStatusAttribute()
    {
        if (is_null($this->actual) || is_null($this->target)) return 'no_data';

        $pct = $this->variance_percent;
        $higher = $this->definition?->higher_is_better ?? true;

        if ($higher) {
            if ($pct >= -5)  return 'on_track';   // within 5% below target = green
            if ($pct >= -15) return 'watch';       // 5-15% below = yellow
            return 'at_risk';                       // >15% below = red
        } else {
            if ($pct <= 5)   return 'on_track';   // within 5% above target = green
            if ($pct <= 15)  return 'watch';
            return 'at_risk';
        }
    }
}