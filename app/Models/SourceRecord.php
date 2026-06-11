<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type',
        'source_url',
        'raw_json',
        'corporate_number',
        'normalized_domain',
        'normalized_phone',
        'name_norm',
        'pref',
        'city',
        'fetched_at',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function sourceLink()
    {
        return $this->hasOne(CompanySourceLink::class);
    }

    public function directorySource()
    {
        return $this->hasOne(DirectorySource::class);
    }
}
