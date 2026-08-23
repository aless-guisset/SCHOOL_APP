<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $fillable = [
        'section_user_id', 'subject_id', 'period', 'grade', 'max_grade',
        'attachment_path', 'attachment_original_name',
        'status', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'grade' => 'float',
        'max_grade' => 'float',
    ];

    public function sectionUser(): BelongsTo
    {
        return $this->belongsTo(SectionUserSchoolRole::class, 'section_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * `grades` n'a pas de colonne `school_id` directe — atteint via
     * subject → course → school_id, comme Subject/Lesson.
     */
    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('subject', fn ($q) => $q->withTrashed()
            ->whereHas('course', fn ($q2) => $q2->withTrashed()->where('school_id', $schoolId)));
    }
}
