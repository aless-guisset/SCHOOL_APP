<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserSchoolRolesController extends Controller
{
    /**
     * Liste des utilisateurs dans l'école active avec leur rôle.
     */
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('admin/web/UserSchoolRoles/Index', [
            'assignments' => UserSchoolRole::where('school_id', $schoolId)
                ->with(['user', 'role'])
                ->orderBy('created_at', 'desc')
                ->paginate(20),
        ]);
    }

    /**
     * Formulaire d'assignation d'un rôle à un utilisateur dans l'école.
     */
    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('admin/web/UserSchoolRoles/Create', [
            'users' => User::where('is_active', true)
                ->orderBy('lastname')
                ->get(['id', 'firstname', 'lastname', 'email']),
            'roles' => Role::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'schoolId' => $schoolId,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $data['school_id']  = $schoolId;
        $data['is_active']  = true;
        $data['status']     = 'A';
        $data['created_by'] = $request->user()->id;

        // Vérifie si la combinaison existe déjà (même avec soft delete)
        $existing = UserSchoolRole::withTrashed()
            ->where('user_id', $data['user_id'])
            ->where('school_id', $schoolId)
            ->where('role_id', $data['role_id'])
            ->first();

        if ($existing) {
            // Restaurer si soft-deleted, sinon erreur
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update(['is_active' => true, 'updated_by' => $request->user()->id]);
            } else {
                return back()->withErrors(['user_id' => 'Cet utilisateur a déjà ce rôle dans cette école.']);
            }
        } else {
            UserSchoolRole::create($data);
        }

        return redirect()->route('user-school-roles.index')
            ->with('flash', ['type' => 'success', 'message' => 'Rôle assigné avec succès.']);
    }

    public function destroy(UserSchoolRole $userSchoolRole)
    {
        $userSchoolRole->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $userSchoolRole->delete();

        return redirect()->route('user-school-roles.index')
            ->with('flash', ['type' => 'success', 'message' => 'Assignation supprimée.']);
    }
}
