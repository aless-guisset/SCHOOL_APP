<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalCertificate extends Model
{
    use SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $fillable = [
        'school_id', 'section_user_id', 'starts_at', 'ends_at', 'reason',
        'attachment_path', 'attachment_original_name',
        'status', 'submitted_by', 'reviewed_by', 'reviewed_at', 'rejection_reason',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $hidden = ['attachment_path'];

    protected $appends = ['has_attachment'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'reviewed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function getHasAttachmentAttribute(): bool
    {
        return (bool) $this->attachment_path;
    }

    public function sectionUser(): BelongsTo
    {
        return $this->belongsTo(SectionUserSchoolRole::class, 'section_user_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }
}
