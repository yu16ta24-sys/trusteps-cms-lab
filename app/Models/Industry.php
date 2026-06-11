<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Industry extends Model
{
    use HasFactory;
    protected $fillable = [
        'slug',
        'name',
        'parent_id',
        'sort_order',
        'is_active',
        'notes',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Industry::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Industry::class, 'parent_id')->orderBy('sort_order');
    }
}
