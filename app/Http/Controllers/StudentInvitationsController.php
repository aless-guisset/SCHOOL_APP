<?php

namespace App\Http\Controllers;

use App\Concerns\GrantsSchoolRoles;
use App\Concerns\PasswordValidationRules;
use App\Models\Role;
use App\Models\StudentInvitation;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\StudentInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentInvitationsController extends Controller
{
    use GrantsSchoolRoles, PasswordValidationRules;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:191']);
        $studentUsr = $this->requireOwnStudentRole($request);

        StudentInvitation::where('student_user_school_role_id', $studentUsr->id)
            ->where('email', $data['email'])
            ->where('is_active', true)
            ->whereNull('accepted_at')
            ->get()
            ->each(function (StudentInvitation $old) use ($request) {
                $old->update(['is_active' => false, 'updated_by' => $request->user()->id]);
                $old->delete();
            });

        $invitation = StudentInvitation::create([
            'student_user_school_role_id' => $studentUsr->id,
            'email' => $data['email'],
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        Notification::route('mail', $data['email'])->notify(new StudentInvitationNotification($invitation));

        return back()->with('flash', ['type' => 'success', 'message' => "Invitation envoyée à {$data['email']}."]);
    }

    public function destroy(Request $request, StudentInvitation $invitation): RedirectResponse
    {
        $studentUsr = $this->requireOwnStudentRole($request);
        abort_unless($invitation->student_user_school_role_id === $studentUsr->id, 404);
        abort_if($invitation->accepted_at !== null, 422, 'Cette invitation a déjà été acceptée.');

        $invitation->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        $invitation->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Invitation annulée.']);
    }

    public function show(string $token): Response
    {
        $invitation = StudentInvitation::where('token', $token)->firstOrFail();
        abort_unless($invitation->isValid(), 404);

        return Inertia::render('auth/StudentInvitationAccept', [
            'email' => $invitation->email,
            'student_name' => "{$invitation->student->user->firstname} {$invitation->student->user->lastname}",
            'account_exists' => User::where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = StudentInvitation::where('token', $token)->firstOrFail();
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
                'profile' => 'student',
            ]);
        }

        $parentRole = Role::where('reference', 'PARENT')->firstOrFail();
        $parentUsr = $this->grantOrRestoreSchoolRole(
            $user->id, $invitation->student->school_id, $parentRole->id, 'A', $invitation->created_by
        );

        $studentUsr = $invitation->student;
        $existing = \App\Models\ParentStudentLink::withTrashed()->firstOrNew([
            'parent_user_school_role_id' => $parentUsr->id,
            'student_user_school_role_id' => $studentUsr->id,
        ]);
        if ($existing->trashed()) {
            $existing->restore();
        }
        $existing->fill([
            'status' => 'A', 'is_active' => true,
            'created_by' => $existing->created_by ?? $invitation->created_by,
            'updated_by' => $invitation->created_by,
        ])->save();

        $invitation->update(['accepted_at' => now()]);

        $request->session()->regenerate();
        Auth::login($user);
        session(['active_school_id' => $invitation->student->school_id]);

        return redirect()->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => 'Accès accordé.']);
    }

    /** Dupliqué de StudentAccessController::requireOwnStudentRole() (même raison que le reste). */
    private function requireOwnStudentRole(Request $request): UserSchoolRole
    {
        $usr = $request->user()->schoolRoles()
            ->where('school_id', session('active_school_id'))
            ->where('status', 'A')
            ->whereHas('role', fn ($q) => $q->where('reference', 'ELEVE'))
            ->first();

        abort_unless($usr, 403);

        return $usr;
    }
}
