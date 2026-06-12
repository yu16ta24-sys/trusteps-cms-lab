<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'axis',
        'value',
        'confidence',
        'auto_suggested_value',
        'algo_version',
        'score_version',
        'reason_json',
        'scored_by',
        'scored_at',
    ];

    protected $casts = [
        'value' => 'integer',
        'confidence' => 'decimal:1',
        'auto_suggested_value' => 'integer',
        'reason_json' => 'array',
        'scored_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
