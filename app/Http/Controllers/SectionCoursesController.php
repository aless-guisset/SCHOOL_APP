<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionCoursesController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/SectionCourses/Index', [
            'sectionCourses' => SectionCourse::whereHas(
                'course', fn ($q) => $q->where('school_id', $schoolId)
            )
                ->with(['course', 'sectionUser.section'])
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/SectionCourses/Create', [
            'courses'      => Course::where('school_id', $schoolId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'sectionUsers' => SectionUserSchoolRole::whereHas(
                'userschoolrole', fn ($q) => $q->where('school_id', $schoolId)
            )
                ->with(['sections', 'userschoolrole.user'])
                ->where('is_active', true)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'section_user_id'   => 'required|integer|exists:section_users,id',
            'course_id'         => 'required|integer|exists:courses,id',
            'name'              => 'required|max:100',
            'total_hours'       => 'required|integer|min:1',
            'hours_per_session' => 'required|integer|min:1',
            'description'       => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active']  = true;

        $sectionCourse = SectionCourse::create($data);

        return redirect()->route('section-courses.show', $sectionCourse)
            ->with('flash', ['type' => 'success', 'message' => 'Association section-cours créée.']);
    }

    public function show(SectionCourse $sectionCourse): Response
    {
        $sectionCourse->load([
            'course',
            'sectionUser.section',
            'schedules',
        ]);

        return Inertia::render('power-user/web/SectionCourses/Show', [
            'sectionCourse' => $sectionCourse,
            'hoursPlanned'  => $sectionCourse->hours_planned,
            'hoursConsumed' => $sectionCourse->hours_consumed,
            'hoursRemaining'=> $sectionCourse->hours_remaining,
            'completion'    => $sectionCourse->completion_percentage,
        ]);
    }

    public function edit(SectionCourse $sectionCourse): Response
    {
        return Inertia::render('power-user/web/SectionCourses/Edit', [
            'sectionCourse' => $sectionCourse->load('course', 'sectionUser.section'),
        ]);
    }

    public function update(Request $request, SectionCourse $sectionCourse)
    {
        $data = $request->validate([
            'name'              => 'sometimes|required|max:100',
            'total_hours'       => 'sometimes|integer|min:1',
            'hours_per_session' => 'sometimes|integer|min:1',
            'description'       => 'sometimes|nullable|string',
            'is_active'         => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $sectionCourse->update($data);

        return redirect()->route('section-courses.show', $sectionCourse)
            ->with('flash', ['type' => 'success', 'message' => 'Association mise à jour.']);
    }

    public function destroy(SectionCourse $sectionCourse)
    {
        $sectionCourse->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $sectionCourse->delete();

        return redirect()->route('section-courses.index')
            ->with('flash', ['type' => 'success', 'message' => 'Association supprimée.']);
    }
}
