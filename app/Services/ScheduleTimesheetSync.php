<?php

namespace App\Services;

use App\Models\School;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Rules\NoTimesheetConflict;
use Carbon\Carbon;

/**
 * Génère/resynchronise les Timesheet futurs d'un Schedule (créneau récurrent)
 * jusqu'à la fin de l'année scolaire de l'école. Les Timesheet marqués
 * is_customized ne sont jamais touchés.
 */
class ScheduleTimesheetSync
{
    public function sync(Schedule $schedule): array
    {
        $this->deleteFutureStandard($schedule);

        $schedule->loadMissing('sectionCourse.course');
        $schoolId = $schedule->sectionCourse?->course?->school_id;
        $yearEndDate = $schoolId ? School::find($schoolId)?->year_end_date : null;

        if (! $schedule->is_active
            || ! $schedule->user_school_role_id
            || ! $schedule->subject_id
            || ! $schedule->classroom_id
            || ! $yearEndDate
        ) {
            return ['created' => 0, 'skipped_conflicts' => 0];
        }

        return $this->generate($schedule, Carbon::today(), Carbon::parse($yearEndDate));
    }

    public function deleteFutureStandard(Schedule $schedule): int
    {
        $count = 0;

        Timesheet::where('schedule_id', $schedule->id)
            ->where('is_customized', false)
            ->where('date', '>=', Carbon::today()->toDateString())
            ->get()
            ->each(function (Timesheet $ts) use (&$count) {
                $ts->delete();
                $count++;
            });

        return $count;
    }

    public function syncSchool(School $school): void
    {
        Schedule::whereHas('sectionCourse.course', fn ($q) => $q->where('school_id', $school->id))
            ->where('is_active', true)
            ->get()
            ->each(fn (Schedule $s) => $this->sync($s));
    }

    private function generate(Schedule $schedule, Carbon $from, Carbon $until): array
    {
        $created = 0;
        $skippedConflicts = 0;

        $cursor = $from->copy();
        while ($cursor->dayOfWeekIso !== (int) $schedule->day_of_week) {
            $cursor->addDay();
        }

        while ($cursor->lte($until)) {
            $alreadyExists = Timesheet::where('schedule_id', $schedule->id)
                ->where('date', $cursor->toDateString())
                ->whereNull('deleted_at')
                ->exists();

            if (! $alreadyExists) {
                $hasConflict = false;
                (new NoTimesheetConflict(
                    scheduleId: $schedule->id,
                    userSchoolRoleId: $schedule->user_school_role_id,
                    classroomId: $schedule->classroom_id,
                ))->validate('date', $cursor->toDateString(), function () use (&$hasConflict) {
                    $hasConflict = true;
                });

                if ($hasConflict) {
                    $skippedConflicts++;
                } else {
                    Timesheet::create([
                        'user_school_role_id' => $schedule->user_school_role_id,
                        'schedule_id'         => $schedule->id,
                        'subject_id'          => $schedule->subject_id,
                        'classroom_id'        => $schedule->classroom_id,
                        'date'                => $cursor->toDateString(),
                        'hours_done'          => 0,
                        'is_active'           => true,
                        'is_customized'       => false,
                        'created_by'          => $schedule->updated_by ?? $schedule->created_by,
                    ]);
                    $created++;
                }
            }

            $cursor->addWeek();
        }

        return ['created' => $created, 'skipped_conflicts' => $skippedConflicts];
    }
}
