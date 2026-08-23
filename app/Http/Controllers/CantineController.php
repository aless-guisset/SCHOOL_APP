<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCanManage;
use App\Models\CantineMenu;
use App\Models\CantineOrder;
use App\Models\School;
use App\Models\SectionUserSchoolRole;
use App\Models\UserSchoolRole;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CantineController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $date = $request->input('date') ? Carbon::parse($request->input('date'))->toDateString() : Carbon::today()->toDateString();

        $menus = CantineMenu::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'label', 'description']);

        $props = [
            'date' => $date,
            'is_past' => Carbon::parse($date)->lt(Carbon::today()),
            'menus' => $menus,
        ];

        if ($this->userCanManage($schoolId)) {
            $orders = CantineOrder::whereIn('cantine_menu_id', $menus->pluck('id'))
                ->where('is_active', true)
                ->with(['sectionUser.userschoolrole.user', 'sectionUser.section', 'menu'])
                ->get();

            $props['roster'] = $orders->map(fn (CantineOrder $o) => [
                'id' => $o->id,
                'name' => $o->sectionUser?->userschoolrole?->user
                    ? "{$o->sectionUser->userschoolrole->user->lastname} {$o->sectionUser->userschoolrole->user->firstname}"
                    : '—',
                'section' => $o->sectionUser?->section?->name,
                'menu_label' => $o->menu?->label,
                'is_present' => $o->is_present,
                'note' => $o->note,
            ])->values();
        } else {
            $sectionUser = $this->currentSectionUser($schoolId);

            $props['can_order'] = (bool) $sectionUser;
            $props['my_order'] = $sectionUser
                ? CantineOrder::where('section_user_id', $sectionUser->id)
                    ->whereDate('date', $date)
                    ->where('is_active', true)
                    ->first(['id', 'cantine_menu_id'])
                : null;
        }

        return Inertia::render('power-user/web/Cantine/Index', $props);
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $data = $request->validate([
            'date' => 'required|date',
            'label' => 'required|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $data['date'] = Carbon::parse($data['date'])->toDateString();
        $data['school_id'] = $schoolId;
        $data['is_active'] = true;
        $data['created_by'] = $request->user()->id;

        CantineMenu::create($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Option de menu ajoutée.']);
    }

    public function destroyMenu(CantineMenu $cantineMenu): RedirectResponse
    {
        abort_unless($cantineMenu->school_id === session('active_school_id'), 404);

        $cantineMenu->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $cantineMenu->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Option de menu supprimée.']);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        return Inertia::render('power-user/web/Cantine/Create', [
            'students' => $this->eligibleStudents($schoolId)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $data = $request->validate([
            'section_user_id' => ['required', 'integer', $this->sectionUserBelongsToSchool($schoolId)],
            'day_of_week' => 'required|integer|min:1|max:7',
        ]);

        // withTrashed() : une inscription retirée puis reprise le même jour ne
        // doit pas se heurter à la contrainte unique (section_user_id, day_of_week).
        $existing = CantineRegistration::withTrashed()
            ->where('section_user_id', $data['section_user_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update(['is_active' => true, 'updated_by' => $request->user()->id]);
            } else {
                return back()->withErrors(['day_of_week' => 'Cet élève est déjà inscrit à la cantine ce jour-là.']);
            }
        } else {
            $data['school_id'] = $schoolId;
            $data['is_active'] = true;
            $data['created_by'] = $request->user()->id;

            CantineRegistration::create($data);
        }

        return redirect()->route('cantine.index')
            ->with('flash', ['type' => 'success', 'message' => 'Inscription cantine enregistrée.']);
    }

    public function destroy(CantineRegistration $cantineRegistration): RedirectResponse
    {
        $cantineRegistration->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $cantineRegistration->delete();

        return redirect()->route('cantine.index')
            ->with('flash', ['type' => 'success', 'message' => 'Inscription cantine supprimée.']);
    }

    public function roster(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $dayOfWeek = $date->dayOfWeekIso;

        $registrations = CantineRegistration::where('school_id', $schoolId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->with(['sectionUser.userschoolrole.user', 'sectionUser.section'])
            ->get();

        $presences = CantinePresence::whereIn('cantine_registration_id', $registrations->pluck('id'))
            ->where('date', $date->toDateString())
            ->get()
            ->keyBy('cantine_registration_id');

        return Inertia::render('power-user/web/Cantine/Roster', [
            'date' => $date->toDateString(),
            'roster' => $registrations->map(function (CantineRegistration $reg) use ($presences) {
                $presence = $presences->get($reg->id);
                $user = $reg->sectionUser?->userschoolrole?->user;

                return [
                    'cantine_registration_id' => $reg->id,
                    'name' => $user ? "{$user->lastname} {$user->firstname}" : '—',
                    'section' => $reg->sectionUser?->section?->name,
                    'is_present' => $presence?->is_present ?? true,
                    'note' => $presence?->note,
                ];
            })->values(),
        ]);
    }

    public function storePresences(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);

        $validRegistrationIds = CantineRegistration::where('school_id', $schoolId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $data = $request->validate([
            'date' => 'required|date',
            'presences' => 'required|array',
            'presences.*.cantine_registration_id' => ['required', 'integer', 'in:'.implode(',', $validRegistrationIds ?: [0])],
            'presences.*.is_present' => 'required|boolean',
            'presences.*.note' => 'nullable|string|max:1000',
        ]);

        foreach ($data['presences'] as $row) {
            $presence = CantinePresence::firstOrNew([
                'cantine_registration_id' => $row['cantine_registration_id'],
                'date' => $data['date'],
            ]);
            $presence->is_present = $row['is_present'];
            $presence->note = $row['note'] ?? null;
            $presence->status = 'A';
            $presence->updated_by = $request->user()->id;
            if (! $presence->exists) {
                $presence->created_by = $request->user()->id;
            }
            $presence->save();
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Présences cantine enregistrées.']);
    }

    /**
     * Résout le `SectionUserSchoolRole` (ligne "élève dans une section") de
     * l'utilisateur authentifié pour l'école active — `null` s'il n'en a pas
     * (pas un élève de cette école, ex: Directeur). Jamais dérivé d'un champ
     * de la requête : c'est ce qui garantit qu'un élève ne peut agir que sur
     * sa propre commande.
     */
    private function currentSectionUser(?int $schoolId): ?SectionUserSchoolRole
    {
        return SectionUserSchoolRole::whereHas(
            'userschoolrole',
            fn ($q) => $q->where('user_id', auth()->id())->where('school_id', $schoolId)
        )->first();
    }

    /** Même liste de rôles que EnsureCanManage — décide quoi renvoyer dans index(), pas un contrôle d'accès en soi. */
    private function userCanManage(?int $schoolId): bool
    {
        $role = UserSchoolRole::with('role')
            ->where('user_id', auth()->id())
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->first()
            ?->role
            ?->name;

        return in_array($role, EnsureCanManage::MANAGE_ROLES, true);
    }

    private function eligibleStudents(?int $schoolId)
    {
        return SectionUserSchoolRole::where('is_active', true)
            ->whereHas('userschoolrole', fn ($q) => $q->where('school_id', $schoolId)
                ->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
            ->with(['userschoolrole.user', 'section']);
    }

    private function abortUnlessCantineEnabled(?int $schoolId): void
    {
        abort_unless(
            $schoolId && School::where('id', $schoolId)->where('cantine_enabled', true)->exists(),
            404
        );
    }

    /**
     * `section_user_id` has no direct `school_id` column — atteint via
     * SectionUserSchoolRole → userschoolrole → school_id. `Rule::exists` ne
     * peut pas exprimer cette relation, d'où une règle en closure.
     */
    private function sectionUserBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! SectionUserSchoolRole::whereHas('userschoolrole', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Cet élève n\'appartient pas à votre établissement.');
            }
        };
    }
}
