<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutreachContact extends Model
{
    protected $fillable = [
        'company_id',
        'phase',
        'contact_method',
        'contacted_at',
        'next_action',
        'next_action_at',
        'memo',
        'created_by',
    ];

    protected $casts = [
        'contacted_at'   => 'datetime',
        'next_action_at' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
