<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopesRouteBindingToActiveSchool
{
    /**
     * Résout le binding de route en le limitant à l'école active en session —
     * empêche un power-user d'accéder à une ressource d'une autre école en
     * devinant son ID dans l'URL. Échec fermé : sans école active, aucun match
     * (Laravel lève ModelNotFoundException → 404, comportement standard).
     *
     * Ne couvre volontairement pas `resolveSoftDeletableRouteBinding()` — si une
     * route venait à être déclarée avec `->withTrashed()`, Laravel appellerait
     * cette autre méthode et contournerait le scoping ci-dessous. Aucune route
     * de ce type n'existe aujourd'hui sur les modèles utilisant ce trait ; à
     * surveiller si `withTrashed()` est ajouté un jour à l'une d'elles. Même
     * remarque pour `resolveChildRouteBinding()` (bindings de route imbriquée) :
     * non surchargée, mais inatteignable aujourd'hui puisqu'aucune route
     * imbriquée n'existe sur ces modèles.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();
        $schoolId = session('active_school_id');

        if (! $schoolId) {
            return null;
        }

        return $this->applySchoolScope($this->where($field, $value), $schoolId)->first();
    }

    abstract protected function applySchoolScope(Builder $query, int $schoolId): Builder;
}
