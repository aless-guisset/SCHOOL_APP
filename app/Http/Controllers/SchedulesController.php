<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\SectionCourse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchedulesController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        $schedules = Schedule::whereHas(
            'sectionCourse.course',
            fn ($q) => $q->where('school_id', $schoolId)
        )
            ->with(['sectionCourse.course'])
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('power-user/web/Schedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Schedules/Create', [
            'sectionCourses' => SectionCourse::whereHas(
                'course', fn ($q) => $q->where('school_id', $schoolId)
            )
                ->with('course')
                ->where('is_active', true)
                ->get(['id', 'name', 'course_id']),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'section_course_id' => ['required', 'integer', $this->sectionCourseBelongsToSchool($schoolId)],
            'name'              => 'required|max:100',
            'day_of_week'       => 'required|integer|between:1,7',
            'start_time'        => 'required|date_format:H:i',
            'end_time'          => 'required|date_format:H:i|after:start_time',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $schedule = Schedule::create($data);

        return redirect()->route('schedules.show', $schedule)
            ->with('flash', ['type' => 'success', 'message' => 'Créneau créé.']);
    }

    public function show(Schedule $schedule): Response
    {
        $schedule->load('sectionCourse.course', 'timesheets.userSchoolRole.user');

        return Inertia::render('power-user/web/Schedules/Show', [
            'schedule' => $schedule,
        ]);
    }

    public function edit(Schedule $schedule): Response
    {
        return Inertia::render('power-user/web/Schedules/Edit', [
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|max:100',
            'day_of_week' => 'sometimes|integer|between:1,7',
            'start_time'  => 'sometimes|date_format:H:i',
            'end_time'    => 'sometimes|date_format:H:i',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $schedule->update($data);

        return redirect()->route('schedules.show', $schedule)
            ->with('flash', ['type' => 'success', 'message' => 'Créneau mis à jour.']);
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('flash', ['type' => 'success', 'message' => 'Créneau supprimé.']);
    }

    /**
     * `section_course_id` has no direct `school_id` column — it's reached via
     * SectionCourse → course → school_id. `Rule::exists` can't express that
     * relation, so use a closure rule instead.
     */
    private function sectionCourseBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! SectionCourse::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Ce cours de section n\'appartient pas à votre établissement.');
            }
        };
    }
}
