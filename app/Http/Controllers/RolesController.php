<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/web/Roles/Index', [
            'roles' => Role::orderBy('name')->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/web/Roles/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|max:50',
            'description' => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $role = Role::create($data);

        return redirect()->route('roles.show', $role)
            ->with('flash', ['type' => 'success', 'message' => 'Rôle créé.']);
    }

    public function show(Role $role): Response
    {
        return Inertia::render('admin/web/Roles/Show', [
            'role' => $role,
        ]);
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('admin/web/Roles/Edit', [
            'role' => $role,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|max:50',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $role->update($data);

        return redirect()->route('roles.show', $role)
            ->with('flash', ['type' => 'success', 'message' => 'Rôle mis à jour.']);
    }

    public function destroy(Role $role)
    {
        $role->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $role->delete();

        return redirect()->route('roles.index')
            ->with('flash', ['type' => 'success', 'message' => 'Rôle supprimé.']);
    }
}
