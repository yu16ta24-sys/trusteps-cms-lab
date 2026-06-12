<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'sort_order',
    ];

    public function scoreObservations()
    {
        return $this->hasMany(IndustryScoreObservation::class);
    }
}
