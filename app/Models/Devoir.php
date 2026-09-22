<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Devoir extends Model
{
    use HasFactory, ScopesRouteBindingToActiveSchool, SoftDeletes;

    protected $fillable = [
        'section_course_id',
        'title',
        'description',
        'due_date',
        'status',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function sectionCourse(): BelongsTo
    {
        return $this->belongsTo(SectionCourse::class);
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('sectionCourse.course', fn ($q) => $q->where('school_id', $schoolId));
    }
}
