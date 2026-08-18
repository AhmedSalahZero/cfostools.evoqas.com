<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataRoomSubsection extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_room_section_id',
        'name',
        'icon',
        'sort_order',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(DataRoomSection::class, 'data_room_section_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'data_room_subsection_id');
    }
}
