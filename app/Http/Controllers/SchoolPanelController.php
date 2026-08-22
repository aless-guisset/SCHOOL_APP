<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\Timesheet;
use App\Models\UserSchoolRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolPanelController extends Controller
{
    public function __invoke(Request $request, School $school): Response
    {
        abort_unless(
            $request->user()->schoolRoles()
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->exists(),
            404
        );

        $schoolId = $school->id;

        // Stats rapides
        $stats = [
            'users'      => UserSchoolRole::where('school_id', $schoolId)->where('is_active', true)->count(),
            'sections'   => Section::where('school_id', $schoolId)->where('is_active', true)->count(),
            'courses'    => Course::where('school_id', $schoolId)->where('is_active', true)->count(),
            'classrooms' => Classroom::where('school_id', $schoolId)->where('is_active', true)->count(),
            'schedules'  => Schedule::whereHas('sectionCourse.course', fn ($q) => $q->where('school_id', $schoolId))->count(),
            'timesheets' => Timesheet::whereHas('userSchoolRole', fn ($q) => $q->where('school_id', $schoolId))->count(),
        ];

        // Prochains créneaux (5 prochains selon le jour de la semaine actuel)
        $upcomingSchedules = Schedule::whereHas(
            'sectionCourse.course', fn ($q) => $q->where('school_id', $schoolId)
        )
            ->with(['sectionCourse.course'])
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'day'        => $s->day_of_week,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time'   => substr($s->end_time, 0, 5),
                'course'     => $s->sectionCourse?->course?->name,
            ]);

        return Inertia::render('school/Panel', [
            'school'            => $school,
            'stats'             => $stats,
            'upcomingSchedules' => $upcomingSchedules,
        ]);
    }
}
