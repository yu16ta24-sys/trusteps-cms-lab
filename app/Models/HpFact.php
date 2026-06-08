<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HpFact extends Model
{
    use HasFactory;

    protected $fillable = [
        'hp_snapshot_id',
        'has_ec',
        'has_reservation',
        'has_recruiting',
        'has_portal_link',
        'has_sns_link',
        'has_google_business_link',
        'has_contact_form',
        'has_public_email',
        'has_phone',
        'contact_method_type',
        'update_status',
        'has_update_targets',
        'cms_type',
        'builder_type',
        'mobile_friendly',
        'ssl_enabled',
        'footer_year_status',
        'portal_dependency_level',
        'extractor_version',
        'extracted_at',
    ];

    protected $casts = [
        'has_ec' => 'boolean',
        'has_reservation' => 'boolean',
        'has_recruiting' => 'boolean',
        'has_portal_link' => 'boolean',
        'has_sns_link' => 'boolean',
        'has_google_business_link' => 'boolean',
        'has_contact_form' => 'boolean',
        'has_public_email' => 'boolean',
        'has_phone' => 'boolean',
        'has_update_targets' => 'boolean',
        'mobile_friendly' => 'boolean',
        'ssl_enabled' => 'boolean',
        'extracted_at' => 'datetime',
    ];

    public function snapshot()
    {
        return $this->belongsTo(HpSnapshot::class, 'hp_snapshot_id');
    }
}
