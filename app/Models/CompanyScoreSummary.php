<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyScoreSummary extends Model
{
    protected $fillable = [
        'company_id',
        'total_score',
        'rank',
        'candidate_type',
        'confidence',
        'flags_json',
        'caps_applied_json',
        'reason_summary',
        'score_version',
    ];

    protected $casts = [
        'total_score'      => 'float',
        'confidence'       => 'float',
        'flags_json'       => 'array',
        'caps_applied_json' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
