<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoursesController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Courses/Index', [
            'courses' => Course::where('school_id', $schoolId)
                ->withCount('subjects')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('power-user/web/Courses/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|max:100',
            'description' => 'nullable|string',
            'reference'   => 'nullable|max:50',
        ]);

        $data['school_id']  = session('active_school_id');
        $data['created_by'] = $request->user()->id;
        $data['is_active']  = true;

        $course = Course::create($data);

        return redirect()->route('courses.show', $course)
            ->with('flash', ['type' => 'success', 'message' => 'Cours créé.']);
    }

    public function show(Course $course): Response
    {
        $course->load('subjects');

        return Inertia::render('power-user/web/Courses/Show', [
            'course' => $course,
        ]);
    }

    public function edit(Course $course): Response
    {
        return Inertia::render('power-user/web/Courses/Edit', [
            'course' => $course,
        ]);
    }

    public function update(Request $request, Course $course)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|max:100',
            'description' => 'sometimes|nullable|string',
            'reference'   => 'sometimes|nullable|max:50',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $course->update($data);

        return redirect()->route('courses.show', $course)
            ->with('flash', ['type' => 'success', 'message' => 'Cours mis à jour.']);
    }

    public function destroy(Course $course)
    {
        $course->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $course->delete();

        return redirect()->route('courses.index')
            ->with('flash', ['type' => 'success', 'message' => 'Cours supprimé.']);
    }
}
