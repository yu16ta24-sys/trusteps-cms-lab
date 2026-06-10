<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndustryScore extends Model
{
    use HasFactory;

    public const SCORE_TYPE_HYPOTHESIS = 'hypothesis';
    public const SCORE_TYPE_OBSERVED = 'observed';
    public const SCORE_TYPE_MIXED = 'mixed';

    protected $fillable = [
        'industry_key',
        'axis_key',
        'value',
        'confidence',
        'score_type',
        'note',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'integer',
        'updated_by' => 'integer',
    ];

    public function axis()
    {
        return $this->belongsTo(IndustryScoreAxis::class, 'axis_key', 'key');
    }

    public function industry()
    {
        return $this->belongsTo(Industry::class, 'industry_key', 'slug');
    }
}
