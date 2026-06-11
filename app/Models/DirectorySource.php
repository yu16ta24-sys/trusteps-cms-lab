<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectorySource extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_record_id',
        'source_type',
        'name',
        'url',
        'normalized_domain',
        'pref_code',
        'pref_name',
        'city',
        'organization_type',
        'status',
        'crawl_status',
        'last_crawled_at',
        'last_error',
        'raw_json',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'last_crawled_at' => 'datetime',
    ];

    public function sourceRecord()
    {
        return $this->belongsTo(SourceRecord::class);
    }

    public function pages()
    {
        return $this->hasMany(DirectorySourcePage::class);
    }

    public function candidatePages()
    {
        return $this->hasMany(DirectorySourcePage::class)->where('status', 'candidate');
    }
}
