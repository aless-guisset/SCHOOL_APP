<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\School;
use App\Models\SchoolInvitation;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\SchoolInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolInvitationsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
            'role_reference' => ['required', 'string', Rule::in(\App\Http\Controllers\SchoolAccessController::JOINABLE_ROLES)],
        ]);

        $school = School::findOrFail(session('active_school_id'));
        $role = Role::where('reference', $data['role_reference'])->firstOrFail();

        $invitation = SchoolInvitation::create([
            'school_id' => $school->id,
            'email' => $data['email'],
            'role_id' => $role->id,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        Notification::route('mail', $data['email'])->notify(new SchoolInvitationNotification($invitation));

        return back()->with('flash', ['type' => 'success', 'message' => "Invitation envoyée à {$data['email']}."]);
    }

    public function show(string $token): Response
    {
        $invitation = SchoolInvitation::where('token', $token)->firstOrFail();
        abort_unless($invitation->isValid(), 404);

        $existingUser = User::where('email', $invitation->email)->exists();

        return Inertia::render('auth/InvitationAccept', [
            'email' => $invitation->email,
            'school_name' => $invitation->school->name,
            'role_name' => $invitation->role->name,
            'account_exists' => $existingUser,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = SchoolInvitation::where('token', $token)->firstOrFail();
        abort_unless($invitation->isValid(), 404);

        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            $data = $request->validate([
                'firstname' => 'required|string|max:100',
                'lastname' => 'required|string|max:100',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'profile' => 'student', // valeur neutre : ce champ ne pilote plus l'inscription pour ce chemin
            ]);
        }

        // withTrashed()->firstOrNew() (plutôt que firstOrCreate()) : une ligne soft-deleted
        // pour ce triplet est invisible à firstOrCreate() (la contrainte UNIQUE n'inclut pas
        // deleted_at), ce qui ferait échouer l'INSERT avec une QueryException → 500. Un
        // status='R' (rejeté) précédent est aussi réactivé plutôt que laissé bloqué.
        $userSchoolRole = UserSchoolRole::withTrashed()->firstOrNew(
            ['user_id' => $user->id, 'school_id' => $invitation->school_id, 'role_id' => $invitation->role_id]
        );

        if ($userSchoolRole->trashed()) {
            $userSchoolRole->restore();
        }

        $userSchoolRole->fill([
            'status' => $userSchoolRole->exists && $userSchoolRole->status !== 'R' ? $userSchoolRole->status : 'A',
            'is_active' => true,
            'created_by' => $userSchoolRole->created_by ?? $invitation->created_by,
            'updated_by' => $invitation->created_by,
        ])->save();

        $invitation->update(['accepted_at' => now()]);

        $request->session()->regenerate();
        Auth::login($user);
        session(['active_school_id' => $invitation->school_id]);

        return redirect()->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => "Bienvenue chez {$invitation->school->name} !"]);
    }
}
