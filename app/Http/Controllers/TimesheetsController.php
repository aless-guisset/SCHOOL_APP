<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\UserSchoolRole;
use App\Rules\NoTimesheetConflict;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetsController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId  = session('active_school_id');
        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'))->startOfWeek(Carbon::MONDAY)->toDateString()
            : now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = Carbon::parse($weekStart)->endOfWeek(Carbon::SUNDAY)->toDateString();

        $timesheets = Timesheet::whereHas(
            'userSchoolRole', fn ($q) => $q->where('school_id', $schoolId)
        )
            ->with(['userSchoolRole.user', 'schedule', 'subject', 'classroom'])
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        return Inertia::render('power-user/web/Timesheets/Index', [
            'timesheets' => $timesheets,
            'week_start' => $weekStart,
        ]);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Timesheets/Create', [
            'userSchoolRoles' => UserSchoolRole::with(['user', 'role'])
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'label' => "{$r->user->lastname} {$r->user->firstname} ({$r->role->name})"]),
            'schedules'  => Schedule::with('sectionCourse.course')
                ->whereHas('sectionCourse')
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(['id', 'name', 'day_of_week', 'start_time', 'end_time', 'section_course_id']),
            'subjects'   => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_school_role_id' => 'required|integer|exists:users_schools_roles,id',
            'schedule_id'         => 'required|integer|exists:schedules,id',
            'subject_id'          => 'required|integer|exists:subjects,id',
            'classroom_id'        => 'required|integer|exists:classrooms,id',
            'date'                => [
                'required',
                'date',
                new NoTimesheetConflict(
                    scheduleId: (int) $request->schedule_id,
                    userSchoolRoleId: (int) $request->user_school_role_id,
                    classroomId: (int) $request->classroom_id,
                ),
            ],
            'hours_done'          => 'required|numeric|min:0',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $timesheet = Timesheet::create($data);

        return redirect()->route('timesheets.show', $timesheet)
            ->with('flash', ['type' => 'success', 'message' => 'Feuille de temps créée.']);
    }

    public function show(Timesheet $timesheet): Response
    {
        $timesheet->load('userSchoolRole.user', 'schedule', 'subject', 'classroom');

        return Inertia::render('power-user/web/Timesheets/Show', [
            'timesheet' => $timesheet,
        ]);
    }

    public function edit(Timesheet $timesheet): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Timesheets/Edit', [
            'timesheet'      => $timesheet->load('userSchoolRole.user', 'schedule', 'subject', 'classroom'),
            'userSchoolRoles' => UserSchoolRole::with(['user', 'role'])
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'label' => "{$r->user->lastname} {$r->user->firstname} ({$r->role->name})"]),
            'schedules'  => Schedule::get(['id', 'name', 'start_time', 'end_time']),
            'subjects'   => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Timesheet $timesheet)
    {
        $scheduleId      = $request->input('schedule_id', $timesheet->schedule_id);
        $userSchoolRoleId = $request->input('user_school_role_id', $timesheet->user_school_role_id);
        $classroomId     = $request->input('classroom_id', $timesheet->classroom_id);

        $data = $request->validate([
            'date'       => [
                'sometimes',
                'date',
                new NoTimesheetConflict(
                    scheduleId: (int) $scheduleId,
                    userSchoolRoleId: (int) $userSchoolRoleId,
                    classroomId: (int) $classroomId,
                    ignoreId: $timesheet->id,
                ),
            ],
            'hours_done' => 'sometimes|numeric|min:0',
            'is_active'  => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $timesheet->update($data);

        return redirect()->route('timesheets.show', $timesheet)
            ->with('flash', ['type' => 'success', 'message' => 'Feuille de temps mise à jour.']);
    }

    public function destroy(Timesheet $timesheet)
    {
        $timesheet->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $timesheet->delete();

        return redirect()->route('timesheets.index')
            ->with('flash', ['type' => 'success', 'message' => 'Feuille de temps supprimée.']);
    }

    public function checkConflict(Request $request): JsonResponse
    {
        $request->validate([
            'schedule_id'         => 'required|integer|exists:schedules,id',
            'date'                => 'required|date',
            'user_school_role_id' => 'required|integer|exists:users_schools_roles,id',
            'classroom_id'        => 'required|integer|exists:classrooms,id',
        ]);

        $schedule  = Schedule::findOrFail($request->schedule_id);
        $startTime = $schedule->start_time;
        $endTime   = $schedule->end_time;
        $conflicts = [];

        $base = DB::table('timesheets as t')
            ->join('schedules as s', 's.id', '=', 't.schedule_id')
            ->whereNull('t.deleted_at')
            ->whereNull('s.deleted_at')
            ->where('t.date', $request->date)
            ->where('s.start_time', '<', $endTime)
            ->where('s.end_time', '>', $startTime);

        if ((clone $base)->where('t.user_school_role_id', (int) $request->user_school_role_id)->exists()) {
            $conflicts[] = 'Ce professeur est déjà occupé sur ce créneau à cette date.';
        }

        if ((clone $base)->where('t.classroom_id', (int) $request->classroom_id)->exists()) {
            $conflicts[] = 'Cette salle est déjà occupée sur ce créneau à cette date.';
        }

        return response()->json(['conflicts' => $conflicts]);
    }
}
