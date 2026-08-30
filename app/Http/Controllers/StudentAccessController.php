<?php

namespace App\Http\Controllers;

use App\Concerns\GrantsSchoolRoles;
use App\Concerns\PasswordValidationRules;
use App\Models\Role;
use App\Models\StudentInvitation;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentAccessController extends Controller
{
    use GrantsSchoolRoles, PasswordValidationRules;

    /**
     * Page "Donner l'accès" de l'élève : génère son code s'il n'en a pas
     * encore, liste les parents déjà liés et les invitations en attente.
     */
    public function show(Request $request): Response
    {
        $studentUsr = $this->requireOwnStudentRole($request);

        if (! $studentUsr->student_access_code) {
            $studentUsr->update(['student_access_code' => $this->generateStudentCode()]);
        }

        $parents = UserSchoolRole::with('user')
            ->where('linked_student_user_school_role_id', $studentUsr->id)
            ->where('status', 'A')
            ->where('is_active', true)
            ->get()
            ->map(fn (UserSchoolRole $p) => [
                'id' => $p->id,
                'name' => "{$p->user->firstname} {$p->user->lastname}",
                'email' => $p->user->email,
            ]);

        $invitations = StudentInvitation::where('student_user_school_role_id', $studentUsr->id)
            ->where('is_active', true)
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StudentInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'expired' => $i->expires_at->isPast(),
                'sent_at' => $i->created_at->diffForHumans(),
            ]);

        return Inertia::render('student/GiveAccess', [
            'access_code' => $studentUsr->student_access_code,
            'parents' => $parents,
            'invitations' => $invitations,
        ]);
    }

    public function regenerateCode(Request $request): RedirectResponse
    {
        $studentUsr = $this->requireOwnStudentRole($request);
        $studentUsr->update(['student_access_code' => $this->generateStudentCode()]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Code régénéré.']);
    }

    public function joinWithCode(Request $request): RedirectResponse
    {
        $rules = ['access_code' => 'required|string'];
        if (! auth()->check()) {
            $rules += $this->accountCreationRules();
        }
        $data = $request->validate($rules);

        $studentUsr = UserSchoolRole::where('student_access_code', $data['access_code'])
            ->where('status', 'A')
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('reference', 'ELEVE'))
            ->first();

        if (! $studentUsr) {
            return back()->withErrors(['access_code' => 'Code invalide.']);
        }

        $user = $this->resolveUser($request, $data);

        $parentRole = Role::where('reference', 'PARENT')->firstOrFail();
        $parentUsr = $this->grantOrRestoreSchoolRole($user->id, $studentUsr->school_id, $parentRole->id, 'A', $user->id);
        $this->linkChild($parentUsr, $studentUsr, $user->id);

        session(['active_school_id' => $studentUsr->school_id]);

        return redirect()->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => 'Accès accordé.']);
    }

    /** L'élève révoque un de ses parents, ou le Directeur de l'école révoque n'importe lequel. */
    public function revoke(Request $request, UserSchoolRole $userSchoolRole): RedirectResponse
    {
        // La route vit hors du groupe `school.context` : l'école active n'est pas
        // garantie en session, et activeRoleAt() n'accepte pas null.
        $schoolId = session('active_school_id') ?? 0;

        // Cet endpoint ne révoque QUE des accès parent, quelle que soit la
        // branche empruntée — invariant explicite, et non simplement garanti par
        // le fait que linked_student_user_school_role_id n'est peuplé que sur
        // des lignes PARENT.
        abort_unless($userSchoolRole->role?->reference === 'PARENT', 403);

        $isOwnStudent = $userSchoolRole->linked_student_user_school_role_id
            && $request->user()->schoolRoles()->where('id', $userSchoolRole->linked_student_user_school_role_id)->exists();
        $isDirecteur = $request->user()->activeRoleAt($schoolId) === 'Directeur'
            && $userSchoolRole->school_id == $schoolId;

        abort_unless($isOwnStudent || $isDirecteur, 403);

        $userSchoolRole->update(['status' => 'R', 'is_active' => false, 'updated_by' => $request->user()->id]);
        $userSchoolRole->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Accès révoqué.']);
    }

    /** Règles de création de compte pour un parent anonyme. Dupliqué volontairement de
     * SchoolAccessController::accountCreationRules() (2 occurrences, pas de troisième prévue). */
    private function accountCreationRules(): array
    {
        return [
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'email' => 'required|email|max:191',
            'password' => $this->passwordRules(),
        ];
    }

    /** Dupliqué volontairement de SchoolAccessController::resolveUser() (même raison). */
    private function resolveUser(Request $request, array $accountData): User
    {
        if (auth()->check()) {
            return $request->user();
        }

        if (User::where('email', $accountData['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Un compte existe déjà avec cet email. Connectez-vous d\'abord.',
            ]);
        }

        $user = User::create([
            'firstname' => $accountData['firstname'],
            'lastname' => $accountData['lastname'],
            'email' => $accountData['email'],
            'password' => Hash::make($accountData['password']),
            'profile' => 'student',
        ]);

        $request->session()->regenerate();
        Auth::login($user);

        return $user;
    }

    /** Crée le lien parent↔enfant s'il n'existe pas déjà (idempotent). */
    private function linkChild(UserSchoolRole $parentUsr, UserSchoolRole $studentUsr, int $actorId): void
    {
        $existing = \App\Models\ParentStudentLink::withTrashed()->firstOrNew([
            'parent_user_school_role_id' => $parentUsr->id,
            'student_user_school_role_id' => $studentUsr->id,
        ]);

        if ($existing->trashed()) {
            $existing->restore();
        }

        $existing->fill([
            'status' => 'A',
            'is_active' => true,
            'created_by' => $existing->created_by ?? $actorId,
            'updated_by' => $actorId,
        ])->save();
    }

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

    /** Même génération que SchoolAccessController::generateAccessCode() (2 occurrences). */
    private function generateStudentCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (UserSchoolRole::where('student_access_code', $code)->exists());

        return $code;
    }
}
