<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id',
        'respondent_name',
        'respondent_title',
        'respondent_company',
        'respondent_gender',
        'respondent_age',
        'ip_address',
    ];

    protected $casts = [
        'respondent_age' => 'integer',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}