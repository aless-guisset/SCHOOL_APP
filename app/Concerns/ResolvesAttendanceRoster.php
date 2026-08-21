<?php

namespace App\Concerns;

use App\Models\SectionUserSchoolRole;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Builder;

trait ResolvesAttendanceRoster
{
    /**
     * Query for the students eligible for attendance on a given timesheet's
     * session: active `section_users` rows, in the session's section, whose
     * user school role has the stable 'ELEVE' role reference.
     *
     * Shared by TimesheetsController::roster() (display) and
     * AttendancesController::store() (validation whitelist) so the two can
     * never drift apart.
     *
     * Returns null when the timesheet's session doesn't resolve to a section
     * (e.g. schedule/sectionCourse/sectionUser chain incomplete).
     */
    protected function eligibleAttendanceStudents(Timesheet $timesheet): ?Builder
    {
        $sectionId = $timesheet->schedule?->sectionCourse?->sectionUser?->section_id;

        if (! $sectionId) {
            return null;
        }

        return SectionUserSchoolRole::where('section_id', $sectionId)
            ->where('is_active', true)
            ->whereHas('userschoolrole', fn ($q) => $q->whereHas('role', fn ($q) => $q->where('reference', 'ELEVE')));
    }
}
