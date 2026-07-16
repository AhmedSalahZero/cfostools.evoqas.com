<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'created_by',
        'name',
        'description',
        'phase',
        'status',
        'start_date',
        'end_date',
        'currency',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function portfolioCompany(): BelongsTo
    {
        return $this->belongsTo(PortfolioCompany::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class)->orderByDesc('expense_date');
    }
}