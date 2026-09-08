<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageStructure
{
    /**
     * Contenu structurel de l'école (sections, cours, matières, salles,
     * leçons, horaires, ressources, cantine) : réservé à Power User et
     * Secrétariat. Le Professeur, contrairement à can-manage (voir
     * EnsureCanManage), n'y a plus accès en écriture — il garde
     * uniquement Feuilles de temps et Notes. Lecture (index/show) non
     * concernée par ce middleware, déjà ouverte à tout rôle de l'école
     * active.
     */
    public const MANAGE_ROLES = ['Power User', 'Secrétariat'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $activeSchoolId = session('active_school_id');

        if (! $user || ! $activeSchoolId) {
            abort(403);
        }

        $role = $user->activeRoleAt($activeSchoolId);

        if (! in_array($role, self::MANAGE_ROLES, true)) {
            abort(403);
        }

        return $next($request);
    }
}
