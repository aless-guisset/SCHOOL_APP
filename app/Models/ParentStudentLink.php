<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentStudentLink extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_user_school_role_id',
        'student_user_school_role_id',
        'status',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parentUserSchoolRole(): BelongsTo
    {
        return $this->belongsTo(UserSchoolRole::class, 'parent_user_school_role_id');
    }

    public function studentUserSchoolRole(): BelongsTo
    {
        return $this->belongsTo(UserSchoolRole::class, 'student_user_school_role_id');
    }
}
