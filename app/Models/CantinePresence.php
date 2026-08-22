<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CantinePresence extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cantine_registration_id', 'date', 'is_present', 'note',
        'status', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_present' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(CantineRegistration::class, 'cantine_registration_id');
    }
}
