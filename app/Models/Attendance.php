<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'timesheet_id', 'section_user_id', 'is_present', 'note',
        'status', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_present' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function sectionUser(): BelongsTo
    {
        return $this->belongsTo(SectionUserSchoolRole::class, 'section_user_id');
    }
}
