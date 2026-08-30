<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrateur
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $activeSchoolId = session('active_school_id');

        if (! $user || ! $activeSchoolId) {
            abort(403);
        }

        // Même résolution que HandleInertiaRequests::share() (currentRole) :
        // rôle le plus privilégié de l'utilisateur dans cette école, pas la
        // première ligne renvoyée par la base.
        $role = $user->activeRoleAt($activeSchoolId);

        if ($role !== 'Administrateur') {
            abort(403);
        }

        return $next($request);
    }
}
