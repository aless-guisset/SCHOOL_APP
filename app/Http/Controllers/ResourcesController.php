<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResourcesController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Resources/Index', [
            'resources' => Resource::where('school_id', $schoolId)
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('power-user/web/Resources/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|max:100',
            'type'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $data['school_id'] = session('active_school_id');
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $resource = Resource::create($data);

        return redirect()->route('resources.show', $resource)
            ->with('flash', ['type' => 'success', 'message' => 'Ressource créée.']);
    }

    public function show(Resource $resource): Response
    {
        return Inertia::render('power-user/web/Resources/Show', [
            'resource' => $resource,
        ]);
    }

    public function edit(Resource $resource): Response
    {
        return Inertia::render('power-user/web/Resources/Edit', [
            'resource' => $resource,
        ]);
    }

    public function update(Request $request, Resource $resource)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|max:100',
            'type'        => 'sometimes|nullable|string|max:50',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $resource->update($data);

        return redirect()->route('resources.show', $resource)
            ->with('flash', ['type' => 'success', 'message' => 'Ressource mise à jour.']);
    }

    public function destroy(Resource $resource)
    {
        $resource->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $resource->delete();

        return redirect()->route('resources.index')
            ->with('flash', ['type' => 'success', 'message' => 'Ressource supprimée.']);
    }
}
