<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomsController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Classrooms/Index', [
            'classrooms' => Classroom::where('school_id', $schoolId)
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('power-user/web/Classrooms/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|max:50',
            'location' => 'nullable|string',
        ]);

        $data['school_id'] = session('active_school_id');
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $classroom = Classroom::create($data);

        return redirect()->route('classrooms.show', $classroom)
            ->with('flash', ['type' => 'success', 'message' => 'Salle créée.']);
    }

    public function show(Classroom $classroom): Response
    {
        return Inertia::render('power-user/web/Classrooms/Show', [
            'classroom' => $classroom,
        ]);
    }

    public function edit(Classroom $classroom): Response
    {
        return Inertia::render('power-user/web/Classrooms/Edit', [
            'classroom' => $classroom,
        ]);
    }

    public function update(Request $request, Classroom $classroom)
    {
        $data = $request->validate([
            'name'      => 'sometimes|required|max:50',
            'location'  => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $classroom->update($data);

        return redirect()->route('classrooms.show', $classroom)
            ->with('flash', ['type' => 'success', 'message' => 'Salle mise à jour.']);
    }

    public function destroy(Classroom $classroom)
    {
        $classroom->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $classroom->delete();

        return redirect()->route('classrooms.index')
            ->with('flash', ['type' => 'success', 'message' => 'Salle supprimée.']);
    }
}
