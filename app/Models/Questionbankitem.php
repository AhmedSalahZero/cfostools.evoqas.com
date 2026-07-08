<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankItem extends Model
{
    protected $fillable = [
        'organization_id',
        'question_bank_section_id',
        'question_text',
        'question_type',
        'is_required',
        'rating_max',
        'placeholder',
        'usage_count',
    ];

    protected $casts = [
        'is_required'  => 'boolean',
        'rating_max'   => 'integer',
        'usage_count'  => 'integer',
    ];

    public function section()
    {
        return $this->belongsTo(QuestionBankSection::class, 'question_bank_section_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionBankItemOption::class)->orderBy('sort_order');
    }
}