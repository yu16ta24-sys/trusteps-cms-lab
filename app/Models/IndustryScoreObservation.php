<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndustryScoreObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'industry_id',
        'axis_key',
        'value',
        'source_company_id',
        'prefecture_id',
        'region_id',
        'observed_at',
    ];

    protected $casts = [
        'value'       => 'integer',
        'observed_at' => 'datetime',
    ];

    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'source_company_id');
    }

    public function prefecture()
    {
        return $this->belongsTo(Prefecture::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
