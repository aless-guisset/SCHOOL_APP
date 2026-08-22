<?php

namespace App\Http\Middleware;

use App\Models\UserSchoolRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManage
{
    /** Aligné sur DashboardController::MANAGE_ROLES et useSchool.ts::canManage. */
    private const MANAGE_ROLES = ['Administrateur', 'Power User', 'Directeur'];

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
