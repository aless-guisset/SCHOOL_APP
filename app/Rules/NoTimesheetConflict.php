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
        $conflicts = $this->find((string) $value);

        if ($conflicts['teacher']) {
            $fail('Ce professeur est déjà occupé sur ce créneau à cette date.');

            return;
        }

        if ($conflicts['classroom']) {
            $fail('Cette salle est déjà occupée sur ce créneau à cette date.');

            return;
        }

        if ($conflicts['section']) {
            $fail('Cette section a déjà un cours planifié sur ce créneau à cette date.');
        }
    }

    /**
     * Recherche, pour une date donnée, l'id du premier Timesheet en conflit
     * pour chacun des trois types (professeur/salle/section) — `null` si
     * aucun. Même détection que `validate()`, exposée séparément pour que
     * l'appelant (ex: pré-check `checkConflict()`) puisse identifier PAR ID
     * quel Timesheet précis bloque, et proposer de le remplacer plutôt que
     * de se contenter d'un message.
     *
     * @return array{teacher: ?int, classroom: ?int, section: ?int}
     */
    public function find(string $date): array
    {
        $schedule = Schedule::find($this->scheduleId);

        if (! $schedule) {
            return ['teacher' => null, 'classroom' => null, 'section' => null];
        }

        $startTime = $schedule->start_time;
        $endTime = $schedule->end_time;

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

        $teacherConflict = (clone $base)->where('t.user_school_role_id', $this->userSchoolRoleId)->value('t.id');
        $classroomConflict = (clone $base)->where('t.classroom_id', $this->classroomId)->value('t.id');

        $sectionConflict = null;
        if ($sectionId) {
            $sectionConflict = (clone $base)
                ->join('sections_courses as sc2', 'sc2.id', '=', 's.section_course_id')
                ->join('section_users as su2', 'su2.id', '=', 'sc2.section_user_id')
                ->where('su2.section_id', $sectionId)
                ->value('t.id');
        }

        return ['teacher' => $teacherConflict, 'classroom' => $classroomConflict, 'section' => $sectionConflict];
    }
}
