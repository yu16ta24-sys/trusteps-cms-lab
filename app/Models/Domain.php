<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'url',
        'normalized_domain',
        'role',
        'is_primary',
        'is_portal',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_portal' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function hpSnapshots()
    {
        return $this->hasMany(HpSnapshot::class);
    }

    public function hpFacts()
    {
        return $this->hasManyThrough(HpFact::class, HpSnapshot::class, 'domain_id', 'hp_snapshot_id');
    }
}

