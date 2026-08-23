<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\UserSchoolRole;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(ScheduleFactory::class)]
class Schedule extends Model
{
    use HasFactory, SoftDeletes, ScopesRouteBindingToActiveSchool;

    // ISO-8601 : 1 = lundi … 7 = dimanche
    const MONDAY = 1;

    const TUESDAY = 2;

    const WEDNESDAY = 3;

    const THURSDAY = 4;

    const FRIDAY = 5;

    const SATURDAY = 6;

    const SUNDAY = 7;

    protected $fillable = [
        'section_course_id',
        'user_school_role_id',
        'subject_id',
        'classroom_id',
        'name',
        'day_of_week',
        'start_time',
        'end_time',
        'reference',
        'description',
        'status',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'day_of_week' => 'integer',
    ];

    public function sectionCourse(): BelongsTo
    {
        return $this->belongsTo(SectionCourse::class, 'section_course_id');
    }

    public function userSchoolRole(): BelongsTo
    {
        return $this->belongsTo(UserSchoolRole::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function getNextOccurrenceAttribute(): string
    {
        $today = now();
        $date = $this->day_of_week >= $today->dayOfWeekIso
            ? $today->copy()->startOfWeek()->addDays($this->day_of_week - 1)
            : $today->copy()->startOfWeek()->addWeek()->addDays($this->day_of_week - 1);

        return $date->format('Y-m-d').' '.$this->start_time;
    }

    public function getIsPassedThisWeekAttribute(): bool
    {
        return $this->day_of_week < now()->dayOfWeekIso;
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('sectionCourse', fn ($q) => $q->withTrashed()
            ->whereHas('course', fn ($q2) => $q2->withTrashed()->where('school_id', $schoolId)));
    }
}
