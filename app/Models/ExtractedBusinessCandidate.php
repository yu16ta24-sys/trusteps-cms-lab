<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractedBusinessCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'directory_source_page_id',
        'directory_source_id',
        'business_name',
        'business_name_kana',
        'address',
        'tel',
        'fax',
        'business_type',
        'description',
        'url_candidate',
        'url_hash',
        'normalized_domain',
        'url_type',
        'url_confidence',
        'sns_urls',
        'detail_page_url',
        'raw_html_block',
        'extraction_method',
        'save_status',
        'source_record_id',
        'raw_json',
    ];

    protected $casts = [
        'sns_urls' => 'array',
        'raw_json' => 'array',
    ];

    public function directorySourcePage()
    {
        return $this->belongsTo(DirectorySourcePage::class);
    }

    public function directorySource()
    {
        return $this->belongsTo(DirectorySource::class);
    }

    public function sourceRecord()
    {
        return $this->belongsTo(SourceRecord::class);
    }
}
