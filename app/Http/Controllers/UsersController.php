<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');

        $users = User::whereHas('schoolRoles', fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('lastname')
            ->paginate(20);

        return Inertia::render('admin/web/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/web/Users/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname'    => 'nullable|max:50',
            'lastname'     => 'nullable|max:50',
            'email'        => 'required|email|unique:users',
            'password'     => 'required|min:6',
            'phone_number' => 'nullable|max:20',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['created_by'] = $request->user()->id;

        $user = User::create($data);

        return redirect()->route('users.show', $user)
            ->with('flash', ['type' => 'success', 'message' => 'Utilisateur créé.']);
    }

    public function show(User $user): Response
    {
        $user->load('schoolRoles.school', 'schoolRoles.role');

        return Inertia::render('admin/web/Users/Show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/web/Users/Edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'firstname'    => 'sometimes|nullable|max:50',
            'lastname'     => 'sometimes|nullable|max:50',
            'email'        => 'sometimes|email|unique:users,email,'.$user->id,
            'password'     => 'sometimes|min:6',
            'phone_number' => 'sometimes|nullable|max:20',
            'is_active'    => 'sometimes|boolean',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $data['updated_by'] = $request->user()->id;
        $user->update($data);

        return redirect()->route('users.show', $user)
            ->with('flash', ['type' => 'success', 'message' => 'Utilisateur mis à jour.']);
    }

    public function destroy(User $user)
    {
        $user->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $user->delete();

        return redirect()->route('users.index')
            ->with('flash', ['type' => 'success', 'message' => 'Utilisateur supprimé.']);
    }
}
