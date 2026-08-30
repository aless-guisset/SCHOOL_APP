<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManage
{
    /**
     * Administrateur = gestion plateforme (écoles, users, rôles, traductions),
     * pas d'autorité sur le contenu académique d'une école en particulier ;
     * Directeur n'a pas ce droit non plus. Aligné sur
     * DashboardController::MANAGE_ROLES et useSchool.ts::canManage.
     */
    public const MANAGE_ROLES = ['Power User', 'Secrétariat', 'Professeur'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $activeSchoolId = session('active_school_id');

        if (! $user || ! $activeSchoolId) {
            abort(403);
        }

        // Même résolution que HandleInertiaRequests::share() (currentRole) :
        // un Professeur qui est aussi Parent dans cette école garde son droit
        // d'écriture, quel que soit l'ordre des lignes en base.
        $role = $user->activeRoleAt($activeSchoolId);

        if (! in_array($role, self::MANAGE_ROLES, true)) {
            abort(403);
        }

        return $next($request);
    }
}
