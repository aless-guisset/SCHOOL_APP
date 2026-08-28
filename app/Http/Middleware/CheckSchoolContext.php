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

        // Routes exclues du check (settings perso, sélection/création d'école,
        // nouveau parcours d'inscription/accès)
        $excludedPrefixes = ['school/', 'settings/', 'api/', 'logout', 'join/', 'invitations/'];
        foreach ($excludedPrefixes as $prefix) {
            if ($request->is($prefix) || $request->is($prefix.'*')) {
                return $next($request);
            }
        }

        $activeSchoolRoles = $user->schoolRoles()->with('school')->where('is_active', true)->where('status', 'A')->get();
        $schoolCount = $activeSchoolRoles->count();

        if ($schoolCount === 0) {
            $hasPendingRequest = $user->schoolRoles()->where('is_active', true)->where('status', 'P')->exists();
            if ($hasPendingRequest) {
                return redirect()->route('join.pending');
            }
            if ($user->profile === 'school_owner') {
                return redirect()->route('school.create');
            }

            return redirect()->route('join.role');
        }

        // Vérifier si l'école en session est encore valide
        $activeSchoolId = session('active_school_id');
        $validSchoolIds = $activeSchoolRoles->pluck('school_id')->toArray();

        if ($activeSchoolId && in_array($activeSchoolId, $validSchoolIds)) {
            return $next($request);
        }

        if ($schoolCount === 1) {
            session(['active_school_id' => $activeSchoolRoles->first()->school_id]);

            return $next($request);
        }

        $defaultSchool = $user->resolveDefaultSchool();
        if ($defaultSchool && in_array($defaultSchool->id, $validSchoolIds)) {
            session(['active_school_id' => $defaultSchool->id]);

            return $next($request);
        }

        return redirect()->route('school.select');
    }
}
