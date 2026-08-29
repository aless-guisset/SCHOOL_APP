<?php

namespace App\Http\Controllers;

use App\Concerns\GrantsSchoolRoles;
use App\Concerns\PasswordValidationRules;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolInvitation;
use App\Models\User;
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
    use GrantsSchoolRoles, PasswordValidationRules;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
            'role_reference' => ['required', 'string', Rule::in(\App\Http\Controllers\SchoolAccessController::JOINABLE_ROLES)],
        ]);

        $school = School::findOrFail(session('active_school_id'));
        $role = Role::where('reference', $data['role_reference'])->firstOrFail();

        // Une nouvelle invitation remplace toute invitation encore active pour
        // la même adresse dans cette école — évite d'avoir plusieurs liens
        // valides en même temps pour la même personne (peu importe le rôle
        // proposé la première fois).
        SchoolInvitation::where('school_id', $school->id)
            ->where('email', $data['email'])
            ->where('is_active', true)
            ->whereNull('accepted_at')
            ->get()
            ->each(function (SchoolInvitation $old) use ($request) {
                $old->update(['is_active' => false, 'updated_by' => $request->user()->id]);
                $old->delete();
            });

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

    public function destroy(SchoolInvitation $schoolInvitation): RedirectResponse
    {
        abort_unless($schoolInvitation->school_id == session('active_school_id'), 404);
        abort_if($schoolInvitation->accepted_at !== null, 422, 'Cette invitation a déjà été acceptée.');

        $schoolInvitation->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $schoolInvitation->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Invitation annulée.']);
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
                'password' => $this->passwordRules(),
            ]);

            $user = User::create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'profile' => 'student', // valeur neutre : ce champ ne pilote plus l'inscription pour ce chemin
            ]);
        }

        $this->grantOrRestoreSchoolRole(
            $user->id, $invitation->school_id, $invitation->role_id, 'A', $invitation->created_by
        );

        $invitation->update(['accepted_at' => now()]);

        $request->session()->regenerate();
        Auth::login($user);
        session(['active_school_id' => $invitation->school_id]);

        return redirect()->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => "Bienvenue chez {$invitation->school->name} !"]);
    }
}
