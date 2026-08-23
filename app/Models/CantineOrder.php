<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CantineOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'section_user_id', 'cantine_menu_id', 'date', 'is_present', 'note',
        'status', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_present' => 'boolean',
        'date' => 'date',
    ];

    public function sectionUser(): BelongsTo
    {
        return $this->belongsTo(SectionUserSchoolRole::class, 'section_user_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(CantineMenu::class, 'cantine_menu_id');
    }
}
