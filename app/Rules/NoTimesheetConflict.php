<?php

namespace App\Rules;

use App\Models\Schedule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie qu'aucun conflit d'horaire n'existe pour un timesheet.
 *
 * Trois types de conflits :
 *   - Professeur déjà occupé sur le même créneau ce jour-là
 *   - Salle déjà occupée sur le même créneau ce jour-là
 *   - Section déjà en cours sur le même créneau ce jour-là
 *
 * Usage dans le contrôleur :
 *   new NoTimesheetConflict(
 *       schedule_id: $request->schedule_id,
 *       userSchoolRoleId: $request->user_school_role_id,
 *       classroomId: $request->classroom_id,
 *       ignoreId: $timesheet->id   // pour les updates
 *   )
 * À appliquer sur le champ 'date'.
 */
class NoTimesheetConflict implements ValidationRule
{
    public function __construct(
        private readonly int $scheduleId,
        private readonly int $userSchoolRoleId,
        private readonly int $classroomId,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $schedule = Schedule::find($this->scheduleId);

        if (! $schedule) {
            return;
        }

        $date       = $value;
        $startTime  = $schedule->start_time;
        $endTime    = $schedule->end_time;

        // Section ID associée au schedule (via SectionCourse → SectionUserSchoolRole)
        $sectionId = DB::table('section_users as su')
            ->join('sections_courses as sc', 'sc.section_user_id', '=', 'su.id')
            ->where('sc.id', $schedule->section_course_id)
            ->value('su.section_id');

        $base = DB::table('timesheets as t')
            ->join('schedules as s', 's.id', '=', 't.schedule_id')
            ->whereNull('t.deleted_at')
            ->whereNull('s.deleted_at')
            ->where('t.date', $date)
            // Chevauchement : start1 < end2 AND end1 > start2
            ->where('s.start_time', '<', $endTime)
            ->where('s.end_time', '>', $startTime);

        if ($this->ignoreId) {
            $base->where('t.id', '!=', $this->ignoreId);
        }

        // Conflit professeur
        if ((clone $base)->where('t.user_school_role_id', $this->userSchoolRoleId)->exists()) {
            $fail('Ce professeur est déjà occupé sur ce créneau à cette date.');
            return;
        }

        // Conflit salle
        if ((clone $base)->where('t.classroom_id', $this->classroomId)->exists()) {
            $fail('Cette salle est déjà occupée sur ce créneau à cette date.');
            return;
        }

        // Conflit section (si la section a pu être résolue)
        if ($sectionId) {
            $sectionConflict = (clone $base)
                ->join('sections_courses as sc2', 'sc2.id', '=', 's.section_course_id')
                ->join('section_users as su2', 'su2.id', '=', 'sc2.section_user_id')
                ->where('su2.section_id', $sectionId)
                ->exists();

            if ($sectionConflict) {
                $fail('Cette section a déjà un cours planifié sur ce créneau à cette date.');
            }
        }
    }
}
