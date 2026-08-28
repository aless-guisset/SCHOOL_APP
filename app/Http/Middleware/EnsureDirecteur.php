<?php

namespace App\Http\Middleware;

use App\Models\UserSchoolRole;
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

        $role = UserSchoolRole::with('role')
            ->where('user_id', $user->id)
            ->where('school_id', $activeSchoolId)
            ->where('is_active', true)
            ->where('status', 'A')
            ->first()
            ?->role
            ?->name;

        if ($role !== 'Directeur') {
            abort(403);
        }

        return $next($request);
    }
}
