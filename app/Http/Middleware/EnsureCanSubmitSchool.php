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
     */
    public function handle(Request $request, Closure $next): Response
    {
        $activeSchoolId = session('active_school_id');

        if ($activeSchoolId && $request->user()->activeRoleAt($activeSchoolId) === 'Élève') {
            abort(403);
        }

        return $next($request);
    }
}
