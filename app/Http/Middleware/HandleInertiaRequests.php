<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Données partagées avec tous les composants Vue via usePage().props
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeSchoolId = session('active_school_id');

        // École active
        $activeSchool = null;
        $currentRole = null;
        $userSchools = [];
        $myChildren = [];
        $parentUsr = null;

        if ($user && $activeSchoolId) {
            $activeSchool = School::find($activeSchoolId);

            // Aligné sur User::activeSchoolRolesAt() (privilège) — évite qu'un
            // utilisateur à plusieurs rôles dans cette école (ex: Professeur ET
            // Parent) voie un rôle différent ici et dans scopedUserSchoolRole().
            $currentRole = $user->activeRoleAt($activeSchoolId);

            // Liste des écoles de l'utilisateur (pour le school switcher)
            $userSchools = $user->schoolRoles()
                ->with('school')
                ->where('is_active', true)
                ->where('status', 'A')
                ->get()
                ->map(fn ($sr) => [
                    'id'         => $sr->school->id,
                    'name'       => $sr->school->name,
                    'is_active'  => $sr->school->id === $activeSchoolId,
                    'is_default' => $sr->school->id === $user->default_school_id,
                ]);

            $parentUsr = $user->schoolRoles()
                ->where('school_id', $activeSchoolId)
                ->where('status', 'A')->where('is_active', true)
                ->whereHas('role', fn ($q) => $q->where('reference', 'PARENT'))
                ->first();

            $parentLinks = $parentUsr
                ? \App\Models\ParentStudentLink::with('studentUserSchoolRole.user')
                    ->where('parent_user_school_role_id', $parentUsr->id)
                    ->where('status', 'A')->where('is_active', true)
                    ->orderBy('id')
                    ->get()
                : collect();

            // Repli sur le premier lien actif (même ordre que la requête
            // ci-dessus) si rien n'est sélectionné en session — cohérent avec
            // User::resolveActiveChild(), qui applique la même règle côté
            // résolution des données réellement affichées au parent.
            $activeChildLinkId = session('active_child_link_id') ?? $parentLinks->first()?->id;

            $myChildren = $parentLinks->map(fn ($link) => [
                'id' => $link->id,
                'name' => $link->studentUserSchoolRole?->user
                    ? "{$link->studentUserSchoolRole->user->firstname} {$link->studentUserSchoolRole->user->lastname}"
                    : '—',
                'is_active' => $link->id === $activeChildLinkId,
            ]);
        }

        return [
            ...parent::share($request),
            'name'        => config('app.name'),
            'auth'        => [
                'user' => $user,
            ],
            'school'      => $activeSchool ? [
                'id'   => $activeSchool->id,
                'name' => $activeSchool->name,
                'cantine_enabled' => $activeSchool->cantine_enabled,
                'access_code' => $currentRole === 'Directeur' ? $activeSchool->access_code : null,
            ] : null,
            'currentRole' => $currentRole,
            'userSchools' => $userSchools,
            'myChildren' => $myChildren,
            'hasParentAccess' => $parentUsr !== null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash'        => $request->session()->get('flash'),
            'locale'       => app()->getLocale(),
            'translations' => TranslationService::getForLocale(app()->getLocale()),
            'pendingCount' => $currentRole === 'Administrateur'
                ? School::where('status', 'P')->count()
                : 0,
            'accessRequestsPendingCount' => $currentRole === 'Directeur' && $activeSchoolId
                ? \App\Models\UserSchoolRole::where('school_id', $activeSchoolId)->where('status', 'P')->where('is_active', true)->count()
                : 0,
            'unreadNotifications' => $user ? [
                'count' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()
                    ->reorder()
                    ->orderByRaw('read_at is null desc')
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn ($n) => [
                        'id'         => $n->id,
                        'title'      => $n->data['title'] ?? '',
                        'body'       => $n->data['body'] ?? '',
                        'url'        => $n->data['url'] ?? null,
                        'read'       => $n->read_at !== null,
                        'created_at' => $n->created_at->diffForHumans(),
                    ]),
            ] : ['count' => 0, 'items' => []],
            'routeName'   => Route::currentRouteName(),
        ];
    }
}
