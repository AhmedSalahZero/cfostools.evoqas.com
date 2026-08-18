<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataRoomSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_company_id',
        'name',
        'icon',
        'sort_order',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function subsections(): HasMany
    {
        return $this->hasMany(DataRoomSubsection::class)->orderBy('sort_order')->orderBy('id');
    }
}
