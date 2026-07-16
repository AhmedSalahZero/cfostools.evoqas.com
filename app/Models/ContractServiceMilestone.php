<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractServiceMilestone extends Model
{
    protected $fillable = [
        'contract_service_id',
        'milestone_index',
        'execution_percentage',
        'amount',
        'start_date',
        'end_date',
        'collection_days',
    ];

    protected $casts = [
        'execution_percentage' => 'float',
        'amount'               => 'float',
        'start_date'           => 'date',
        'end_date'             => 'date',
        'collection_days'      => 'integer',
    ];

    public function contractService(): BelongsTo
    {
        return $this->belongsTo(ContractService::class);
    }
}
