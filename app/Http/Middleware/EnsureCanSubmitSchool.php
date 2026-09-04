<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanSubmitSchool
{
    /**
     * Un Élève ne peut pas soumettre une nouvelle école — il appartient déjà
     * à une école active. Un compte sans école active (fondateur fraîchement
     * inscrit, aucune ligne UserSchoolRole encore) doit rester libre de
     * soumettre : ce cas n'est jamais bloqué ici.
     *
     * Résolu directement sur les lignes UserSchoolRole actives de l'utilisateur,
     * PAS via session('active_school_id') : cette route est volontairement
     * exclue de CheckSchoolContext (cf. routes/web.php, "sans school.context"),
     * donc active_school_id peut ne pas encore être en session à ce stade
     * (ex: un élève qui accède à /school/create juste après connexion, sans
     * être passé par une page qui l'établit) — s'appuyer sur la session
     * laisserait passer ce cas au lieu de le bloquer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasActiveStudentRole = $request->user()->schoolRoles()
            ->where('is_active', true)
            ->where('status', 'A')
            ->whereHas('role', fn ($q) => $q->where('name', 'Élève'))
            ->exists();

        if ($hasActiveStudentRole) {
            abort(403);
        }

        return $next($request);
    }
}
