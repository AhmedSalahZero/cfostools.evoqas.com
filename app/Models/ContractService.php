<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractService extends Model
{
    protected $fillable = [
        'customer_contract_id',
        'name',
        'description',
        'amount',
        'start_date',
        'end_date',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'amount'     => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(CustomerContract::class, 'customer_contract_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractServiceMilestone::class)->orderBy('milestone_index');
    }
}
