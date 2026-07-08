<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    protected $fillable = [
        'survey_id',
        'question_text',
        'question_type',
        'sort_order',
        'is_required',
        'placeholder',
        'rating_max',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'rating_max'  => 'integer',
        'sort_order'  => 'integer',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function options()
    {
        return $this->hasMany(SurveyQuestionOption::class)->orderBy('sort_order');
    }

    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}