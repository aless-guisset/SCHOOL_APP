<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\School;
use App\Models\UserSchoolRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolAccessController extends Controller
{
    /** Rôles auto-inscriptibles — jamais Directeur ni Administrateur. */
    public const JOINABLE_ROLES = ['PROF', 'SEC', 'POWER'];

    public function joinWithCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'access_code' => 'required|string',
            'role_reference' => ['required', 'string', Rule::in(self::JOINABLE_ROLES)],
        ]);

        $school = School::where('access_code', $data['access_code'])->where('status', 'A')->first();

        if (! $school) {
            return back()->withErrors(['access_code' => 'Code d\'accès invalide.']);
        }

        $role = Role::where('reference', $data['role_reference'])->firstOrFail();
        $user = $request->user();

        UserSchoolRole::firstOrCreate(
            ['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id],
            ['status' => 'A', 'is_active' => true, 'created_by' => $user->id]
        );

        session(['active_school_id' => $school->id]);

        return redirect()->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => "Vous avez rejoint {$school->name}."]);
    }

    public function joinRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            // ELEVE est accepté ici (contrairement à joinWithCode(), qui ne l'autorise
            // jamais) car le parcours étudiant sans code envoie légitimement 'ELEVE' —
            // la valeur n'est de toute façon jamais utilisée telle quelle quand
            // is_student=true (voir $roleReference plus bas), mais elle doit d'abord
            // passer la validation pour atteindre ce point.
            'role_reference' => ['required', 'string', Rule::in([...self::JOINABLE_ROLES, 'ELEVE'])],
            'is_student' => 'required|boolean',
        ]);

        $school = School::where('id', $data['school_id'])->where('status', 'A')->firstOrFail();
        $user = $request->user();

        // `role_reference` du client n'est JAMAIS pris tel quel pour un étudiant —
        // toujours forcé à ELEVE côté serveur, quoi que le formulaire ait envoyé.
        $roleReference = $data['is_student'] ? 'ELEVE' : $data['role_reference'];
        $role = Role::where('reference', $roleReference)->firstOrFail();

        UserSchoolRole::firstOrCreate(
            ['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id],
            ['status' => 'P', 'is_active' => true, 'created_by' => $user->id]
        );

        $directeurRole = Role::where('reference', 'DIR')->first();
        if ($directeurRole) {
            $directeurs = \App\Models\User::whereHas('schoolRoles', fn ($q) => $q
                ->where('school_id', $school->id)->where('status', 'A')->where('role_id', $directeurRole->id))
                ->get();
            \Illuminate\Support\Facades\Notification::send(
                $directeurs,
                new \App\Notifications\AccessRequestSubmittedNotification($school, $user)
            );
        }

        return redirect()->route('join.pending')
            ->with('flash', ['type' => 'success', 'message' => 'Votre demande a été envoyée.']);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $schools = School::where('status', 'A')
            ->where('is_active', true)
            ->where('name', 'like', '%'.$request->string('q')->trim().'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($schools);
    }
}
