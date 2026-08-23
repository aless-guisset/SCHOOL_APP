<?php

namespace App\Http\Middleware;

use App\Models\UserSchoolRole;
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
    private const MANAGE_ROLES = ['Power User', 'Secrétariat', 'Professeur'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $activeSchoolId = session('active_school_id');

        if (! $user || ! $activeSchoolId) {
            abort(403);
        }

        $role = UserSchoolRole::with('role')
            ->where('user_id', $user->id)
            ->where('school_id', $activeSchoolId)
            ->where('is_active', true)
            ->first()
            ?->role
            ?->name;

        if (! in_array($role, self::MANAGE_ROLES, true)) {
            abort(403);
        }

        return $next($request);
    }
}
