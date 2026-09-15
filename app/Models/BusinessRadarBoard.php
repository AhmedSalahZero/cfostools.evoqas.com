<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRadarBoard extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'name',
        'description',
        'direction_label',
        'created_by',
    ];

    // Default label for the "Discussed Direction" section (rest of the
    // schema always stores the type as 'direction'; only the display
    // label is user-editable, per board).
    const DEFAULT_DIRECTION_LABEL = 'Discussed Direction';

    protected $appends = ['direction_label_display'];

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

    public function directions()
    {
        return $this->items()->where('type', 'direction');
    }

    public function getDirectionLabelDisplayAttribute(): string
    {
        return $this->direction_label ?: self::DEFAULT_DIRECTION_LABEL;
    }
}
