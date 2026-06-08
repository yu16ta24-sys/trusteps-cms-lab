<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyKillFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'flag',
        'note',
        'source',
        'flagged_by',
        'flagged_at',
    ];

    protected $casts = [
        'flagged_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
