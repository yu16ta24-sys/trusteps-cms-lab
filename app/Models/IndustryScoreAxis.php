<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndustryScoreAxis extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'category',
        'min_value',
        'max_value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_value' => 'integer',
        'max_value' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scores()
    {
        return $this->hasMany(IndustryScore::class, 'axis_key', 'key');
    }
}
