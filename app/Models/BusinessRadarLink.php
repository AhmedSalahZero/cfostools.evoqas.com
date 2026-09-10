<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRadarLink extends Model
{
    protected $fillable = [
        'challenge_item_id',
        'potential_item_id',
        'strength',
        'note',
    ];

    protected $casts = [
        'strength' => 'integer',
    ];

    public function challenge()
    {
        return $this->belongsTo(BusinessRadarItem::class, 'challenge_item_id');
    }

    public function potential()
    {
        return $this->belongsTo(BusinessRadarItem::class, 'potential_item_id');
    }
}
