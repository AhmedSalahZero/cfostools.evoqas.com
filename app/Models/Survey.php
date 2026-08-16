<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $fillable = [
        'portfolio_company_id',
        'organization_id',
        'created_by',
        'title',
        'introduction',
        'prepared_by',
        'default_respondent_name',
        'default_respondent_title',
        'default_respondent_company',
        'show_respondent_age',
        'show_respondent_gender',
        'link_token',
        'status',
        'is_template',
        'response_count',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'show_respondent_age' => 'boolean',
        'show_respondent_gender' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(PortfolioCompany::class, 'portfolio_company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order');
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class);
    }
}