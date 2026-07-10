<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSchoolContext
{
    /**
     * Après authentification, s'assure que l'utilisateur a un contexte école actif.
     *
     * Logique :
     * - 0 école  → redirige vers /school/create (formulaire de soumission)
     * - 1 école  → met l'école en session et continue
     * - N écoles → redirige vers /school/select si aucune école en session
     *              (ou si l'école en session n'appartient plus à l'utilisateur)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Routes exclues du check (settings perso, sélection/création d'école)
        $excludedPrefixes = ['school/', 'settings/', 'api/', 'logout'];
        foreach ($excludedPrefixes as $prefix) {
            if ($request->is($prefix) || $request->is($prefix.'*')) {
                return $next($request);
            }
        }

        $schoolRoles = $user->schoolRoles()->with('school')->where('is_active', true)->get();
        $schoolCount = $schoolRoles->count();

        if ($schoolCount === 0) {
            // Étudiant sans école : page d'attente d'invitation
            if ($user->profile === 'student') {
                return redirect()->route('school.waiting');
            }
            // Créateur d'école : formulaire de demande
            return redirect()->route('school.create');
        }

        // Vérifier si l'école en session est encore valide
        $activeSchoolId = session('active_school_id');
        $validSchoolIds = $schoolRoles->pluck('school_id')->toArray();

        if ($activeSchoolId && in_array($activeSchoolId, $validSchoolIds)) {
            // Session valide, on continue
            return $next($request);
        }

        if ($schoolCount === 1) {
            // Une seule école : on la met en session automatiquement
            session(['active_school_id' => $schoolRoles->first()->school_id]);

            return $next($request);
        }

        // Plusieurs écoles : résoudre l'école par défaut ou demander de choisir
        $defaultSchool = $user->resolveDefaultSchool();
        if ($defaultSchool && in_array($defaultSchool->id, $validSchoolIds)) {
            session(['active_school_id' => $defaultSchool->id]);

            return $next($request);
        }

        return redirect()->route('school.select');
    }
}
