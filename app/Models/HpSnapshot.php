<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HpSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'crawl_type',
        'snapshot_version',
        'requested_url',
        'final_url',
        'http_status',
        'raw_html_path',
        'text_path',
        'screenshot_pc_path',
        'screenshot_sp_path',
        'meta_json_path',
        'error_type',
        'error_message',
        'observation_note',
        'crawled_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'crawled_at' => 'datetime',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function fact()
    {
        return $this->hasOne(HpFact::class);
    }
}
