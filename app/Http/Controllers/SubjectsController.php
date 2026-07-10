<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectsController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Subjects/Index', [
            'subjects' => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->with('course')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Subjects/Create', [
            'courses' => Course::where('school_id', $schoolId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'name'        => 'required|max:100',
            'description' => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $subject = Subject::create($data);

        return redirect()->route('subjects.show', $subject)
            ->with('flash', ['type' => 'success', 'message' => 'Matière créée.']);
    }

    public function show(Subject $subject): Response
    {
        $subject->load('course');

        return Inertia::render('power-user/web/Subjects/Show', [
            'subject' => $subject,
        ]);
    }

    public function edit(Subject $subject): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Subjects/Edit', [
            'subject' => $subject->load('course'),
            'courses' => Course::where('school_id', $schoolId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Subject $subject)
    {
        $data = $request->validate([
            'course_id'   => 'sometimes|integer|exists:courses,id',
            'name'        => 'sometimes|required|max:100',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $subject->update($data);

        return redirect()->route('subjects.show', $subject)
            ->with('flash', ['type' => 'success', 'message' => 'Matière mise à jour.']);
    }

    public function destroy(Subject $subject)
    {
        $subject->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('flash', ['type' => 'success', 'message' => 'Matière supprimée.']);
    }
}
