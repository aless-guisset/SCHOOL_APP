<?php

namespace App\Http\Controllers;

use App\Concerns\CreatesSchoolInvitations;
use App\Concerns\GrantsSchoolRoles;
use App\Concerns\PasswordValidationRules;
use App\Models\School;
use App\Models\SchoolInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolInvitationsController extends Controller
{
    use CreatesSchoolInvitations, GrantsSchoolRoles, PasswordValidationRules;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
            'role_reference' => ['required', 'string', Rule::in(\App\Http\Controllers\SchoolAccessController::JOINABLE_ROLES)],
        ]);

        $school = School::findOrFail(session('active_school_id'));

        $this->createSchoolInvitation($school, $data['email'], $data['role_reference'], $request->user()->id);

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
