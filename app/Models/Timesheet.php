<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Database\Factories\TimesheetFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(TimesheetFactory::class)]
class Timesheet extends Model
{
    use HasFactory, SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $fillable = [
        'user_school_role_id',
        'schedule_id',
        'subject_id',
        'classroom_id',
        'date',
        'hours_done',
        'reference',
        'description',
        'status',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    // ---- Relations ----

    public function userSchoolRole(): BelongsTo
    {
        return $this->belongsTo(UserSchoolRole::class);
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('userSchoolRole', fn ($q) => $q->where('school_id', $schoolId));
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function getSectionCourseAttribute()
    {
        return $this->schedule?->sectionCourse;
    }
}
