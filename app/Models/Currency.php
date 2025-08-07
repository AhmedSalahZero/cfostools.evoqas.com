<?php

namespace App\Models;

use App\Models\Traits\Accessors\CurrencyAccessor;
use App\Models\Traits\Relations\CurrencyRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory , CurrencyRelation , CurrencyAccessor;
}
