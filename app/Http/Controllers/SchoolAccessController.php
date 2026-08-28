<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\AccessRequestSubmittedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolAccessController extends Controller
{
    /** Rôles auto-inscriptibles — jamais Directeur ni Administrateur. */
    public const JOINABLE_ROLES = ['PROF', 'SEC', 'POWER'];

    public function joinWithCode(Request $request): RedirectResponse
    {
        $rules = [
            'access_code' => 'required|string',
            'role_reference' => ['required', 'string', Rule::in(self::JOINABLE_ROLES)],
        ];

        if (! auth()->check()) {
            $rules += $this->accountCreationRules();
        }

        $data = $request->validate($rules);

        $school = School::where('access_code', $data['access_code'])->where('status', 'A')->first();

        if (! $school) {
            return back()->withErrors(['access_code' => 'Code d\'accès invalide.']);
        }

        $role = Role::where('reference', $data['role_reference'])->firstOrFail();
        $user = $this->resolveUser($request, $data);

        $this->grantOrRestoreRole($user, $school->id, $role->id, 'A');

        session(['active_school_id' => $school->id]);

        return redirect()->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => "Vous avez rejoint {$school->name}."]);
    }

    public function joinRequest(Request $request): RedirectResponse
    {
        $rules = [
            'school_id' => 'required|integer|exists:schools,id',
            // ELEVE est accepté ici (contrairement à joinWithCode(), qui ne l'autorise
            // jamais) car le parcours étudiant sans code envoie légitimement 'ELEVE' —
            // la valeur n'est de toute façon jamais utilisée telle quelle quand
            // is_student=true (voir $roleReference plus bas), mais elle doit d'abord
            // passer la validation pour atteindre ce point.
            'role_reference' => ['required', 'string', Rule::in([...self::JOINABLE_ROLES, 'ELEVE'])],
            'is_student' => 'required|boolean',
        ];

        if (! auth()->check()) {
            $rules += $this->accountCreationRules();
        }

        $data = $request->validate($rules);

        $school = School::where('id', $data['school_id'])->where('status', 'A')->firstOrFail();
        $user = $this->resolveUser($request, $data);

        // `role_reference` du client n'est JAMAIS pris tel quel pour un étudiant —
        // toujours forcé à ELEVE côté serveur, quoi que le formulaire ait envoyé.
        $roleReference = $data['is_student'] ? 'ELEVE' : $data['role_reference'];
        $role = Role::where('reference', $roleReference)->firstOrFail();

        $this->grantOrRestoreRole($user, $school->id, $role->id, 'P');

        $directeurRole = Role::where('reference', 'DIR')->first();
        if ($directeurRole) {
            $directeurs = User::whereHas('schoolRoles', fn ($q) => $q
                ->where('school_id', $school->id)->where('status', 'A')->where('role_id', $directeurRole->id))
                ->get();
            Notification::send($directeurs, new AccessRequestSubmittedNotification($school, $user));
        }

        return redirect()->route('join.pending')
            ->with('flash', ['type' => 'success', 'message' => 'Votre demande a été envoyée.']);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        // Échappe les caractères spéciaux LIKE (%, _) pour qu'une recherche telle
        // que "q=%" ne matche pas silencieusement toutes les écoles.
        $escaped = str_replace(['%', '_'], ['\%', '\_'], $request->string('q')->trim()->value());

        $schools = School::where('status', 'A')
            ->where('is_active', true)
            ->where('name', 'like', '%'.$escaped.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($schools);
    }

    /** Règles de création de compte pour un visiteur anonyme qui rejoint via code/demande. */
    private function accountCreationRules(): array
    {
        return [
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'email' => 'required|email|max:191',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    /**
     * Authentifié → utilisateur courant, comportement inchangé (rejoindre une école
     * supplémentaire). Anonyme → crée le compte et connecte l'utilisateur, même
     * convention que SchoolInvitationsController::accept(), SAUF si un compte existe
     * déjà avec cet email : pas de prise de contrôle silencieuse, l'utilisateur est
     * renvoyé vers le formulaire avec une erreur l'invitant à se connecter d'abord.
     */
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
            'profile' => 'student', // valeur neutre : ce champ ne pilote plus l'inscription pour ce chemin
        ]);

        $request->session()->regenerate();
        Auth::login($user);

        return $user;
    }

    /**
     * Octroie (ou restaure) un UserSchoolRole pour ce triplet user/school/role.
     * Gère deux pièges : une ligne soft-deleted invisible à firstOrCreate (la contrainte
     * UNIQUE(user_id, school_id, role_id) n'inclut pas deleted_at → 500 sur INSERT), et
     * une ligne status='R' (rejetée) qu'il faut réactiver plutôt que laisser bloquée pour
     * toujours. Un statut déjà 'A' ou 'P' existant est préservé tel quel (idempotent) ;
     * $defaultStatus ne s'applique qu'aux lignes neuves ou précédemment rejetées.
     */
    private function grantOrRestoreRole(User $user, int $schoolId, int $roleId, string $defaultStatus): UserSchoolRole
    {
        $userSchoolRole = UserSchoolRole::withTrashed()->firstOrNew(
            ['user_id' => $user->id, 'school_id' => $schoolId, 'role_id' => $roleId]
        );

        if ($userSchoolRole->trashed()) {
            $userSchoolRole->restore();
        }

        $userSchoolRole->fill([
            'status' => $userSchoolRole->exists && $userSchoolRole->status !== 'R' ? $userSchoolRole->status : $defaultStatus,
            'is_active' => true,
            'created_by' => $userSchoolRole->created_by ?? $user->id,
            'updated_by' => $user->id,
        ])->save();

        return $userSchoolRole;
    }

    public function regenerateCode(Request $request): RedirectResponse
    {
        $school = School::findOrFail(session('active_school_id'));
        $school->update(['access_code' => $this->generateAccessCode(), 'updated_by' => $request->user()->id]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Code d\'accès régénéré.']);
    }

    /** Même génération que SchoolsController::generateAccessCode() — dupliqué
     * volontairement ici (2 occurrences, pas de troisième prévue) plutôt que
     * d'extraire un service pour 2 appelants seulement. */
    private function generateAccessCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (School::where('access_code', $code)->exists());

        return $code;
    }
}
