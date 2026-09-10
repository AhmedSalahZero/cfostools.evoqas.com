<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRadarItem extends Model
{
    protected $fillable = [
        'business_radar_board_id',
        'type',
        'title',
        'description',
        'duration_months',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'duration_months' => 'integer',
    ];

    // 999 is the internal sentinel for "more than 24 months"
    const OVER_24 = 999;

    // Ordered list of selectable durations -> display label.
    // Order matters: position in this list is the "duration rank" used
    // for charting and speed banding.
    const DURATION_OPTIONS = [
        1   => '1 month',
        2   => '2 months',
        3   => '3 months',
        4   => '4 months',
        5   => '5 months',
        6   => '6 months',
        7   => '7 months',
        8   => '8 months',
        9   => '9 months',
        10  => '10 months',
        11  => '11 months',
        12  => '12 months',
        18  => '18 months',
        24  => '24 months',
        self::OVER_24 => 'More than 24 months',
    ];

    const PHASE_QUICK_WIN = 'quick_win';
    const PHASE_LONG_TERM = 'long_term';
    const PHASE_SHORT_TERM = 'short_term';
    const PHASE_MONITOR = 'monitor';

    const PHASE_LABELS = [
        self::PHASE_QUICK_WIN  => 'Quick win',
        self::PHASE_SHORT_TERM => 'Short-term',
        self::PHASE_LONG_TERM  => 'Long-term / structural',
        self::PHASE_MONITOR    => 'Monitor',
    ];

    const RESOLVED_STATUSES = ['resolved', 'captured'];

    public function board()
    {
        return $this->belongsTo(BusinessRadarBoard::class, 'business_radar_board_id');
    }

    // Business areas this item touches, each with its own impact weight (pivot).
    public function areas()
    {
        return $this->belongsToMany(
            BusinessRadarArea::class,
            'business_radar_item_areas',
            'business_radar_item_id',
            'business_radar_area_id'
        )->withPivot('impact_score')->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Links where this item is the challenge side
    public function linksAsChallenge()
    {
        return $this->hasMany(BusinessRadarLink::class, 'challenge_item_id');
    }

    // Links where this item is the potential side
    public function linksAsPotential()
    {
        return $this->hasMany(BusinessRadarLink::class, 'potential_item_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', self::RESOLVED_STATUSES);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', self::RESOLVED_STATUSES);
    }

    /**
     * The item's overall impact = the highest impact weight across all
     * the business areas it's linked to (an item hitting Cash Flow at 5
     * and Cost at 3 is treated, and plotted, as a 5 — its worst impact).
     * Falls back to 3 (Moderate) if it has no areas linked at all.
     */
    public function getImpactScoreAttribute(): int
    {
        if (!$this->relationLoaded('areas')) {
            $this->load('areas');
        }
        return $this->areas->max('pivot.impact_score') ?? 3;
    }

    public function getDurationLabelAttribute(): string
    {
        return self::DURATION_OPTIONS[$this->duration_months] ?? "{$this->duration_months} months";
    }

    /**
     * 1-based position of this item's duration within DURATION_OPTIONS
     * (1 = fastest / "1 month", 15 = slowest / "more than 24 months").
     * Used to place the item on the quadrant chart's x-axis.
     */
    public function getDurationRankAttribute(): int
    {
        $position = array_search($this->duration_months, array_keys(self::DURATION_OPTIONS), true);
        return $position === false ? count(self::DURATION_OPTIONS) : $position + 1;
    }

    /**
     * Speed score 1-5, derived from duration rank in even bands of 3:
     * rank 1-3 (<=3mo) = 5 (fastest) ... rank 13-15 (18/24/>24mo) = 1 (slowest).
     */
    public function getSpeedScoreAttribute(): int
    {
        return 6 - (int) ceil($this->duration_rank / 3);
    }

    /**
     * Priority score = Impact x Speed (max 25). Highest impact done
     * fastest ranks first — this is what the top-management priority
     * list is sorted by.
     */
    public function getPriorityScoreAttribute(): int
    {
        return $this->impact_score * $this->speed_score;
    }

    /**
     * Combined score = this item's own priority score, plus a share of
     * every linked item's own priority score — scaled by that link's
     * strength (Weak = 50%, Medium = 75%, Strong = 100%). A link never
     * contributes more than the full value of what it's connected to,
     * and an unlinked item's combined score is identical to its own.
     */
    public function getCombinedPriorityScoreAttribute(): float
    {
        $strengthFactors = [1 => 0.5, 2 => 0.75, 3 => 1.0];
        $bonus = 0;

        $links = $this->type === 'challenge' ? $this->linksAsChallenge : $this->linksAsPotential;

        foreach ($links as $link) {
            $linked = $this->type === 'challenge' ? $link->potential : $link->challenge;
            if (!$linked) {
                continue;
            }
            $factor = $strengthFactors[$link->strength] ?? 0.75;
            $bonus += $linked->priority_score * $factor;
        }

        return round($this->priority_score + $bonus, 1);
    }

    /**
     * Classic impact/speed quadrant:
     *  high impact + fast  = quick win
     *  high impact + slow  = long-term / structural
     *  low impact  + fast  = short-term
     *  low impact  + slow  = monitor / deprioritize
     */
    public function getPhaseAttribute(): string
    {
        $highImpact = $this->impact_score >= 4;
        $fast       = $this->speed_score >= 4; // duration rank 1-6, i.e. <= 6 months

        if ($highImpact && $fast) {
            return self::PHASE_QUICK_WIN;
        }
        if ($highImpact && !$fast) {
            return self::PHASE_LONG_TERM;
        }
        if (!$highImpact && $fast) {
            return self::PHASE_SHORT_TERM;
        }
        return self::PHASE_MONITOR;
    }

    public function getPhaseLabelAttribute(): string
    {
        return self::PHASE_LABELS[$this->phase];
    }
}
