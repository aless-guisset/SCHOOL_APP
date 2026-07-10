<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonsController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Lessons/Index', [
            'lessons' => Lesson::where('school_id', $schoolId)
                ->with('subject.course')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Lessons/Create', [
            'subjects' => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)
                ->with('course')
                ->orderBy('name')
                ->get(['id', 'name', 'course_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject_id'  => 'required|integer|exists:subjects,id',
            'name'        => 'required|max:100',
            'description' => 'nullable|string',
        ]);

        $data['school_id'] = session('active_school_id');
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $lesson = Lesson::create($data);

        return redirect()->route('lessons.show', $lesson)
            ->with('flash', ['type' => 'success', 'message' => 'Leçon créée.']);
    }

    public function show(Lesson $lesson): Response
    {
        $lesson->load('subject.course');

        return Inertia::render('power-user/web/Lessons/Show', [
            'lesson' => $lesson,
        ]);
    }

    public function edit(Lesson $lesson): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Lessons/Edit', [
            'lesson'   => $lesson->load('subject'),
            'subjects' => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $data = $request->validate([
            'subject_id'  => 'sometimes|integer|exists:subjects,id',
            'name'        => 'sometimes|required|max:100',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $lesson->update($data);

        return redirect()->route('lessons.show', $lesson)
            ->with('flash', ['type' => 'success', 'message' => 'Leçon mise à jour.']);
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $lesson->delete();

        return redirect()->route('lessons.index')
            ->with('flash', ['type' => 'success', 'message' => 'Leçon supprimée.']);
    }
}
