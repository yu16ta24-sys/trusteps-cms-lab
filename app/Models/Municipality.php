<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    use HasFactory;

    protected $fillable = [
        'prefecture_id',
        'code',
        'name',
        'municipality_scale',
        'population_band',
        'city_type',
        'is_prefectural_capital',
        'is_designated_city',
        'is_core_city',
        'is_remote_area',
    ];

    protected $casts = [
        'is_prefectural_capital' => 'boolean',
        'is_designated_city' => 'boolean',
        'is_core_city' => 'boolean',
        'is_remote_area' => 'boolean',
    ];

    public function prefecture()
    {
        return $this->belongsTo(Prefecture::class);
    }
}
