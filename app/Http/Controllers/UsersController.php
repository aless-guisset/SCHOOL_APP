<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Export CSV RGPD : données personnelles des utilisateurs de l'école active.
     * Couvre le droit d'accès/portabilité (art. 15/20 RGPD) — un admin peut
     * remettre cet export à un utilisateur qui demande ses données, ou l'utiliser
     * comme registre pour une demande d'audit.
     */
    public function export(Request $request): StreamedResponse
    {
        $schoolId = session('active_school_id');

        $users = User::whereHas('schoolRoles', fn ($q) => $q->where('school_id', $schoolId))
            ->with(['schoolRoles' => fn ($q) => $q->where('school_id', $schoolId)->with('role')])
            ->orderBy('lastname')
            ->get();

        $filename = 'utilisateurs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour qu'Excel affiche correctement les accents.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Prénom', 'Nom', 'Email', 'Téléphone', 'Rôle(s)', 'Actif', 'Créé le']);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->firstname,
                    $user->lastname,
                    $user->email,
                    $user->phone_number,
                    $user->schoolRoles->map(fn ($usr) => $usr->role?->name)->filter()->implode(', '),
                    $user->is_active ? 'Oui' : 'Non',
                    $user->created_at?->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
