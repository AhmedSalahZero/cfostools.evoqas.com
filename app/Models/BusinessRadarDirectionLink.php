<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRadarDirectionLink extends Model
{
    protected $fillable = [
        'direction_item_id',
        'linked_item_id',
        'note',
    ];

    // The "direction" side of the link.
    public function direction()
    {
        return $this->belongsTo(BusinessRadarItem::class, 'direction_item_id');
    }

    // The Challenge or Potential item being referenced.
    public function linkedItem()
    {
        return $this->belongsTo(BusinessRadarItem::class, 'linked_item_id');
    }
}
