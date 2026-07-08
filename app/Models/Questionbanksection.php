<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankSection extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(QuestionBankItem::class, 'question_bank_section_id')->orderByDesc('usage_count');
    }
}