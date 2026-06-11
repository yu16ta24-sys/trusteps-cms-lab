<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectorySourcePage extends Model
{
    use HasFactory;

    protected $fillable = [
        'directory_source_id',
        'url',
        'url_hash',
        'normalized_domain',
        'title',
        'link_text',
        'page_type',
        'status',
        'score',
        'confidence',
        'discovered_from',
        'raw_json',
        'type_score',
        'extraction_status',
        'last_seen_at',
        'last_fetched_at',
        'fetch_error',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'last_seen_at' => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    public function directorySource()
    {
        return $this->belongsTo(DirectorySource::class);
    }

    public function extractedBusinessCandidates()
    {
        return $this->hasMany(ExtractedBusinessCandidate::class);
    }
}

