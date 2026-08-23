<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesAttendanceRoster;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\UserSchoolRole;
use App\Notifications\TimesheetAssignedNotification;
use App\Notifications\TimesheetCancelledNotification;
use App\Rules\NoTimesheetConflict;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetsController extends Controller
{
    use ResolvesAttendanceRoster;

    private const PERIODS = ['week', 'month', 'trimester'];

    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $period = in_array($request->input('period'), self::PERIODS, true) ? $request->input('period') : 'week';
        $anchor = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : now();
        $sectionId = $request->integer('section_id') ?: null;

        [$rangeStart, $rangeEnd] = match ($period) {
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            // Trimestre = fenêtre glissante de 3 mois calendaires à partir du
            // mois de l'ancre (pas encore aligné sur un calendrier scolaire
            // précis — dépendra de la localité de l'école, à venir).
            'trimester' => [$anchor->copy()->startOfMonth(), $anchor->copy()->startOfMonth()->addMonths(2)->endOfMonth()],
        };

        $timesheets = Timesheet::whereHas(
            'userSchoolRole', fn ($q) => $q->where('school_id', $schoolId)
        )
            ->when($sectionId, fn ($q) => $q->whereHas(
                'schedule.sectionCourse.sectionUser',
                fn ($q2) => $q2->where('section_id', $sectionId)
            ))
            ->with(['userSchoolRole.user', 'schedule', 'subject', 'classroom'])
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderBy('date')
            ->get();

        return Inertia::render('power-user/web/Timesheets/Index', [
            'timesheets' => $timesheets,
            'period' => $period,
            'anchor_date' => $anchor->toDateString(),
            'range_start' => $rangeStart->toDateString(),
            'range_end' => $rangeEnd->toDateString(),
            // Gardé pour WeeklyCalendar (grille visuelle, valable seulement en vue semaine).
            'week_start' => $anchor->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'sections' => Section::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'section_id' => $sectionId,
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
            'schedules'  => Schedule::whereHas(
                'sectionCourse.course', fn ($q) => $q->where('school_id', $schoolId)
            )
                ->where('is_active', true)
                ->with('sectionCourse.sectionUser.section')
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'day_of_week' => $s->day_of_week,
                    'start_time' => $s->start_time, 'end_time' => $s->end_time,
                    'section_id' => $s->sectionCourse?->sectionUser?->section_id,
                    'section_name' => $s->sectionCourse?->sectionUser?->section?->name,
                ]),
            'sections' => Section::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'subjects'   => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'user_school_role_id' => ['required', 'integer', Rule::exists('users_schools_roles', 'id')->where('school_id', $schoolId)],
            'schedule_id'         => ['required', 'integer', $this->scheduleBelongsToSchool($schoolId)],
            'subject_id'          => ['required', 'integer', $this->subjectBelongsToSchool($schoolId)],
            'classroom_id'        => ['required', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
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

        $teacher = UserSchoolRole::with('user')->find($timesheet->user_school_role_id);
        if ($teacher?->user && $teacher->user->id !== $request->user()->id) {
            $teacher->user->notify(new TimesheetAssignedNotification($timesheet, $request->user()));
        }

        return redirect()->route('timesheets.show', $timesheet)
            ->with('flash', ['type' => 'success', 'message' => 'Feuille de temps créée.']);
    }

    public function show(Timesheet $timesheet): Response
    {
        $timesheet->load('userSchoolRole.user', 'schedule', 'subject', 'classroom');

        return Inertia::render('power-user/web/Timesheets/Show', [
            'timesheet' => $timesheet,
            'roster'    => $this->roster($timesheet),
        ]);
    }

    private function roster(Timesheet $timesheet): array
    {
        $students = $this->eligibleAttendanceStudents($timesheet);

        if (! $students) {
            return [];
        }

        $attendances = Attendance::where('timesheet_id', $timesheet->id)
            ->get()
            ->keyBy('section_user_id');

        return $students
            ->with('userschoolrole.user')
            ->get()
            ->map(function (SectionUserSchoolRole $su) use ($attendances) {
                $attendance = $attendances->get($su->id);

                return [
                    'section_user_id' => $su->id,
                    'name'             => $su->userschoolrole?->user
                        ? "{$su->userschoolrole->user->lastname} {$su->userschoolrole->user->firstname}"
                        : '—',
                    'is_present' => $attendance?->is_present ?? true,
                    'note'       => $attendance?->note,
                ];
            })
            ->values()
            ->all();
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
            'schedules'  => Schedule::whereHas('sectionCourse.course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(['id', 'name', 'day_of_week', 'start_time', 'end_time']),
            'subjects'   => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Timesheet $timesheet)
    {
        $schoolId = session('active_school_id');

        $scheduleId       = $request->input('schedule_id', $timesheet->schedule_id);
        $userSchoolRoleId = $request->input('user_school_role_id', $timesheet->user_school_role_id);
        $classroomId      = $request->input('classroom_id', $timesheet->classroom_id);

        $data = $request->validate([
            'user_school_role_id' => ['sometimes', 'integer', Rule::exists('users_schools_roles', 'id')->where('school_id', $schoolId)],
            'schedule_id'         => ['sometimes', 'integer', $this->scheduleBelongsToSchool($schoolId)],
            'subject_id'          => ['sometimes', 'integer', $this->subjectBelongsToSchool($schoolId)],
            'classroom_id'        => ['sometimes', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
            'date'                => [
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

        $manualFields = ['schedule_id', 'subject_id', 'classroom_id', 'user_school_role_id'];
        $isCustomized = collect($manualFields)->contains(
            fn ($field) => array_key_exists($field, $data) && (int) $data[$field] !== (int) $timesheet->{$field}
        );
        if ($isCustomized) {
            $data['is_customized'] = true;
        }

        $data['updated_by'] = $request->user()->id;
        $timesheet->update($data);

        return redirect()->route('timesheets.show', $timesheet)
            ->with('flash', ['type' => 'success', 'message' => 'Feuille de temps mise à jour.']);
    }

    public function destroy(Request $request, Timesheet $timesheet)
    {
        $teacher = UserSchoolRole::with('user')->find($timesheet->user_school_role_id);
        $date = $timesheet->date;

        $timesheet->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        $timesheet->delete();

        if ($teacher?->user && $teacher->user->id !== $request->user()->id) {
            $teacher->user->notify(new TimesheetCancelledNotification($date, $request->user()));
        }

        return redirect()->route('timesheets.index')
            ->with('flash', ['type' => 'success', 'message' => 'Feuille de temps supprimée.']);
    }

    public function checkConflict(Request $request): JsonResponse
    {
        $schoolId = session('active_school_id');

        $request->validate([
            'schedule_id'         => ['required', 'integer', $this->scheduleBelongsToSchool($schoolId)],
            'date'                => 'required|date',
            'user_school_role_id' => ['required', 'integer', Rule::exists('users_schools_roles', 'id')->where('school_id', $schoolId)],
            'classroom_id'        => ['required', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
        ]);

        $conflicts = [];

        (new NoTimesheetConflict(
            scheduleId: (int) $request->schedule_id,
            userSchoolRoleId: (int) $request->user_school_role_id,
            classroomId: (int) $request->classroom_id,
        ))->validate('date', $request->date, function (string $message) use (&$conflicts) {
            $conflicts[] = $message;
        });

        return response()->json(['conflicts' => $conflicts]);
    }

    /**
     * `schedule_id` has no direct `school_id` column — it's reached via
     * Schedule → sectionCourse → course → school_id. `Rule::exists` can't
     * express that multi-hop relation, so use a closure rule instead.
     */
    private function scheduleBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! Schedule::whereHas('sectionCourse.course', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Ce créneau n\'appartient pas à votre établissement.');
            }
        };
    }

    /**
     * `subject_id` has no direct `school_id` column — it's reached via
     * Subject → course → school_id. `Rule::exists` can't express that
     * relation, so use a closure rule instead.
     */
    private function subjectBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Cette matière n\'appartient pas à votre établissement.');
            }
        };
    }
}
