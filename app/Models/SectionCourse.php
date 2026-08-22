<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Database\Factories\SectionCourseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(SectionCourseFactory::class)]
class SectionCourse extends Model
{
    use HasFactory, SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $table = 'sections_courses';

    protected $fillable = [
        'section_user_id',
        'course_id',
        'total_hours',
        'hours_per_session',
        'name',
        'reference',
        'description',
        'status',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_hours' => 'integer',
        'hours_per_session' => 'integer',
    ];

    // ---- Relations ----

    public function sectionUser(): BelongsTo
    {
        return $this->belongsTo(SectionUserSchoolRole::class, 'section_user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('course', fn ($q) => $q->where('school_id', $schoolId));
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'section_course_id');
    }

    public function timesheets(): HasManyThrough
    {
        return $this->hasManyThrough(Timesheet::class, Schedule::class, 'section_course_id', 'schedule_id');
    }

    // ---- Accessors ----

    /**
     * Heures planifiées = nombre de schedules × hours_per_session
     */
    public function getHoursPlannedAttribute(): int
    {
        return $this->schedules->count() * $this->hours_per_session;
    }

    /**
     * Heures réellement réalisées = somme des timesheets
     */
    public function getHoursConsumedAttribute(): int
    {
        return (int) $this->timesheets()->sum('hours_done');
    }

    /**
     * Heures restantes avant d'atteindre le plafond
     */
    public function getHoursRemainingAttribute(): int
    {
        return max(0, $this->total_hours - $this->hours_consumed);
    }

    /**
     * Vérifie si on peut encore ajouter X heures sans dépasser le plafond
     */
    public function hasCapacity(int $hours): bool
    {
        return ($this->hours_consumed + $hours) <= $this->total_hours;
    }

    /**
     * Vérifie si on peut encore ajouter un schedule sans dépasser le plafond
     */
    public function canAddSchedule(): bool
    {
        return $this->hasCapacity($this->hours_per_session);
    }

    /**
     * % de complétion basé sur les heures réalisées
     */
    public function getCompletionPercentageAttribute(): float
    {
        if ($this->total_hours === 0) {
            return 0;
        }

        return round(min(100, ($this->hours_consumed / $this->total_hours) * 100), 2);
    }

    /**
     * Prochaine session planifiée
     */
    public function getNextSessionDateAttribute(): ?string
    {
        $today = now();
        $next = $this->schedules
            ->sortBy('day_of_week')
            ->first(fn ($s) => $s->day_of_week >= $today->dayOfWeekIso);

        if (! $next) {
            $next = $this->schedules->sortBy('day_of_week')->first();
            if (! $next) {
                return null;
            }
            $date = $today->copy()->next($next->day_of_week);
        } else {
            $date = $today->copy()->startOfWeek()->addDays($next->day_of_week - 1);
        }

        return $date->format('Y-m-d').' '.$next->start_time;
    }
}
