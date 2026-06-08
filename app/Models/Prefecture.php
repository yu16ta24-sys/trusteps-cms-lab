<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prefecture extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_kana',
        'prefecture_scale',
    ];

    public function municipalities()
    {
        return $this->hasMany(Municipality::class);
    }
}
