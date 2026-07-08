<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FsLineItem extends Model
{
    protected $fillable = [
        'fs_section_id',
        'label',
        'amount',
        'cf_category',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function section()
    {
        return $this->belongsTo(FsSection::class, 'fs_section_id');
    }

    public function settlementSchedules()
    {
        return $this->hasMany(FsSettlementSchedule::class, 'fs_line_item_id');
    }
}