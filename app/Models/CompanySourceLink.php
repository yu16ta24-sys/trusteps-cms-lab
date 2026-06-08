<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySourceLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'source_record_id',
        'match_type',
        'match_confidence',
        'created_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceRecord()
    {
        return $this->belongsTo(SourceRecord::class);
    }
}
