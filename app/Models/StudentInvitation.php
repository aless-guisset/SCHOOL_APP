<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentInvitation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_user_school_role_id',
        'email',
        'token',
        'expires_at',
        'accepted_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(UserSchoolRole::class, 'student_user_school_role_id');
    }

    /** Lien à usage unique : invalide si expiré, déjà accepté, ou désactivé. */
    public function isValid(): bool
    {
        return $this->is_active && $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
