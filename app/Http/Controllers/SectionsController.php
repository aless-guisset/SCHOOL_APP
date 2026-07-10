<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionsController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Sections/Index', [
            'sections' => Section::where('school_id', $schoolId)
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('power-user/web/Sections/Create');
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

        $section = Section::create($data);

        return redirect()->route('sections.show', $section)
            ->with('flash', ['type' => 'success', 'message' => 'Section créée.']);
    }

    public function show(Section $section): Response
    {
        $section->load('sectionUsers.userSchoolRole.user');

        return Inertia::render('power-user/web/Sections/Show', [
            'section' => $section,
        ]);
    }

    public function edit(Section $section): Response
    {
        return Inertia::render('power-user/web/Sections/Edit', [
            'section' => $section,
        ]);
    }

    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|max:100',
            'description' => 'sometimes|nullable|string',
            'reference'   => 'sometimes|nullable|max:50',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $section->update($data);

        return redirect()->route('sections.show', $section)
            ->with('flash', ['type' => 'success', 'message' => 'Section mise à jour.']);
    }

    public function destroy(Section $section)
    {
        $section->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $section->delete();

        return redirect()->route('sections.index')
            ->with('flash', ['type' => 'success', 'message' => 'Section supprimée.']);
    }
}
