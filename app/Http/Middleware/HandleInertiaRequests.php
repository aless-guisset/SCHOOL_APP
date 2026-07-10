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

        if ($user && $activeSchoolId) {
            $activeSchool = School::find($activeSchoolId);

            // Rôle de l'utilisateur dans l'école active
            $schoolRole = $user->schoolRoles()
                ->with('role')
                ->where('school_id', $activeSchoolId)
                ->where('is_active', true)
                ->first();

            $currentRole = $schoolRole?->role?->name;

            // Liste des écoles de l'utilisateur (pour le school switcher)
            $userSchools = $user->schoolRoles()
                ->with('school')
                ->where('is_active', true)
                ->get()
                ->map(fn ($sr) => [
                    'id'         => $sr->school->id,
                    'name'       => $sr->school->name,
                    'is_active'  => $sr->school->id === $activeSchoolId,
                    'is_default' => $sr->school->id === $user->default_school_id,
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
            ] : null,
            'currentRole' => $currentRole,
            'userSchools' => $userSchools,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash'        => $request->session()->get('flash'),
            'locale'       => app()->getLocale(),
            'translations' => TranslationService::getForLocale(app()->getLocale()),
            'pendingCount' => $currentRole === 'Administrateur'
                ? School::where('status', 'P')->count()
                : 0,
            'routeName'   => Route::currentRouteName(),
        ];
    }
}
