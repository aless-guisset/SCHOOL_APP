<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDirecteur
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $activeSchoolId = session('active_school_id');

        if (! $user || ! $activeSchoolId) {
            abort(403);
        }

        // Même résolution que HandleInertiaRequests::share() (currentRole) :
        // un compte à plusieurs rôles dans une école (ex: Directeur ET Parent)
        // doit être jugé ici sur le rôle le plus privilégié, pas sur la
        // première ligne renvoyée par la base.
        $role = $user->activeRoleAt($activeSchoolId);

        if ($role !== 'Directeur') {
            abort(403);
        }

        return $next($request);
    }
}
