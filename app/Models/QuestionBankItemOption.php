<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankItemOption extends Model
{
    protected $fillable = [
        'question_bank_item_id',
        'option_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(QuestionBankItem::class, 'question_bank_item_id');
    }
}