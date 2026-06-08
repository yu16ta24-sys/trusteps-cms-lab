<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'merged_into_id',
        'merge_previous_status',
        'municipality_id',
        'industry_id',
        'primary_domain_id',
        'legal_name',
        'display_name',
        'name_norm',
        'alias_names_json',
        'corporate_number',
        'pref',
        'city',
        'is_killed',
        'merged_at',
        'merged_by',
        'merge_reason',
        'hp_observation_json',
        'hp_observation_note',
        'hp_observed_at',
        'hp_observed_by',
    ];

    protected $casts = [
        'alias_names_json' => 'array',
        'is_killed' => 'boolean',
        'merged_at' => 'datetime',
        'hp_observation_json' => 'array',
        'hp_observed_at' => 'datetime',
    ];

    public function mergedInto()
    {
        return $this->belongsTo(Company::class, 'merged_into_id');
    }

    public function mergedChildren()
    {
        return $this->hasMany(Company::class, 'merged_into_id');
    }

    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function primaryDomain()
    {
        return $this->belongsTo(Domain::class, 'primary_domain_id');
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function sourceLinks()
    {
        return $this->hasMany(CompanySourceLink::class);
    }

    public function killFlags()
    {
        return $this->hasMany(CompanyKillFlag::class);
    }

    public function scores()
    {
        return $this->hasMany(CompanyScore::class);
    }
}
