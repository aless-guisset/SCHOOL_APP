<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CantineMenu extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'date', 'label', 'description',
        'status', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'date' => 'date',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(CantineOrder::class);
    }
}
