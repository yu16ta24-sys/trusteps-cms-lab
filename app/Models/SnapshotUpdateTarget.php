<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SnapshotUpdateTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'hp_snapshot_id',
        'update_target_id',
        'is_present',
        'is_stopped',
        'last_update_date',
        'evidence_json',
        'extractor_version',
    ];

    protected $casts = [
        'is_present' => 'boolean',
        'is_stopped' => 'boolean',
        'last_update_date' => 'date',
        'evidence_json' => 'array',
    ];

    public function snapshot()
    {
        return $this->belongsTo(HpSnapshot::class, 'hp_snapshot_id');
    }

    public function updateTarget()
    {
        return $this->belongsTo(UpdateTarget::class);
    }
}
