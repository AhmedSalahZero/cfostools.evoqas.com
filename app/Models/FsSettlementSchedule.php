<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FsSettlementSchedule extends Model
{
    protected $fillable = [
        'fs_line_item_id',
        'month',
        'amount',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function lineItem()
    {
        return $this->belongsTo(FsLineItem::class, 'fs_line_item_id');
    }
}