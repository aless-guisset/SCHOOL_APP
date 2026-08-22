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
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'subject_id'  => ['required', 'integer', $this->subjectBelongsToSchool($schoolId)],
            'name'        => 'required|max:100',
            'description' => 'nullable|string',
        ]);

        $data['school_id'] = $schoolId;
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
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'subject_id'  => ['sometimes', 'integer', $this->subjectBelongsToSchool($schoolId)],
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
