<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\UserSchoolRole;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchedulesController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $sectionId = $request->integer('section_id') ?: null;
        $user = $request->user();
        $viewingChild = null;

        if ($request->boolean('as_parent')) {
            // Vue "Mes enfants" (double rôle) : voir DashboardController::index()
            // pour l'invariant de sécurité — ne jamais utiliser le rôle réel de
            // l'appelant ici, sous peine de mélanger ses propres données avec
            // celles de l'enfant.
            $usr = $user->parentLinkedStudent($schoolId ?? 0);
            $currentRole = 'Élève';
            if ($usr?->user) {
                $viewingChild = "{$usr->user->firstname} {$usr->user->lastname}";
            }
        } else {
            $usr = $user->scopedUserSchoolRole($schoolId ?? 0);
            $currentRole = $user->activeRoleAt($schoolId ?? 0);
        }

        $allowedSectionUserIds = $this->resolveAllowedSectionUserIds($usr, $currentRole);

        $schedules = Schedule::whereHas(
            'sectionCourse.course',
            fn ($q) => $q->where('school_id', $schoolId)
        )
            ->when($allowedSectionUserIds !== null, fn ($q) => $q->whereHas(
                'sectionCourse', fn ($q2) => $q2->whereIn('section_user_id', $allowedSectionUserIds)
            ))
            ->when($sectionId, fn ($q) => $q->whereHas(
                'sectionCourse.sectionUser',
                fn ($q2) => $q2->where('section_id', $sectionId)
            ))
            ->with(['sectionCourse.course'])
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $school = \App\Models\School::find($schoolId);

        $sectionsQuery = Section::where('school_id', $schoolId)->where('is_active', true);
        if ($allowedSectionUserIds !== null) {
            $allowedSectionIds = SectionUserSchoolRole::whereIn('id', $allowedSectionUserIds)
                ->pluck('section_id')->unique();
            $sectionsQuery->whereIn('id', $allowedSectionIds);
        }

        return Inertia::render('power-user/web/Schedules/Index', [
            'schedules' => $schedules,
            'sections' => $sectionsQuery->orderBy('name')->get(['id', 'name']),
            'section_id' => $sectionId,
            'school' => $school ? [
                'id' => $school->id,
                'year_end_date' => $school->year_end_date?->toDateString(),
            ] : null,
            'viewing_child' => $viewingChild,
        ]);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Schedules/Create', [
            'sectionCourses' => SectionCourse::whereHas(
                'course', fn ($q) => $q->where('school_id', $schoolId)
            )
                ->with('course')
                ->where('is_active', true)
                ->get(['id', 'name', 'course_id']),
            'userSchoolRoles' => UserSchoolRole::with(['user', 'role'])
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->where('status', 'A')
                ->whereHas('role', fn ($q) => $q->where('reference', 'PROF'))
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'label' => "{$r->user->lastname} {$r->user->firstname} ({$r->role->name})"]),
            'subjects' => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'section_course_id'   => ['required', 'integer', $this->sectionCourseBelongsToSchool($schoolId)],
            'name'                => 'required|max:100',
            'day_of_week'         => 'required|integer|between:1,7',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i|after:start_time',
            'user_school_role_id' => ['nullable', 'integer', Rule::exists('users_schools_roles', 'id')->where('school_id', $schoolId)],
            'subject_id'          => ['nullable', 'integer', $this->subjectBelongsToSchool($schoolId)],
            'classroom_id'        => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        $schedule = Schedule::create($data);

        return $this->redirectAfterWrite($request, $schedule, 'Créneau créé.');
    }

    public function show(Request $request, Schedule $schedule): Response
    {
        $schoolId = session('active_school_id');
        $user = $request->user();

        if ($request->boolean('as_parent')) {
            $usr = $user->parentLinkedStudent($schoolId ?? 0);
            $currentRole = 'Élève';
        } else {
            $usr = $user->scopedUserSchoolRole($schoolId ?? 0);
            $currentRole = $user->activeRoleAt($schoolId ?? 0);
        }

        $allowedSectionUserIds = $this->resolveAllowedSectionUserIds($usr, $currentRole);

        if ($allowedSectionUserIds !== null) {
            abort_unless(
                $allowedSectionUserIds->contains($schedule->sectionCourse?->section_user_id),
                404
            );
        }

        $schedule->load('sectionCourse.course', 'timesheets.userSchoolRole.user');

        return Inertia::render('power-user/web/Schedules/Show', [
            'schedule' => $schedule,
        ]);
    }

    public function edit(Schedule $schedule): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Schedules/Edit', [
            'schedule' => $schedule,
            'userSchoolRoles' => UserSchoolRole::with(['user', 'role'])
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->where('status', 'A')
                ->whereHas('role', fn ($q) => $q->where('reference', 'PROF'))
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'label' => "{$r->user->lastname} {$r->user->firstname} ({$r->role->name})"]),
            'subjects' => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('school_id', $schoolId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'name'                => 'sometimes|required|max:100',
            'day_of_week'         => 'sometimes|integer|between:1,7',
            'start_time'          => 'sometimes|date_format:H:i',
            'end_time'            => 'sometimes|date_format:H:i',
            'is_active'           => 'sometimes|boolean',
            'user_school_role_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users_schools_roles', 'id')->where('school_id', $schoolId)],
            'subject_id'          => ['sometimes', 'nullable', 'integer', $this->subjectBelongsToSchool($schoolId)],
            'classroom_id'        => ['sometimes', 'nullable', 'integer', Rule::exists('classrooms', 'id')->where('school_id', $schoolId)],
        ]);

        $data['updated_by'] = $request->user()->id;
        $schedule->update($data);

        return $this->redirectAfterWrite($request, $schedule, 'Créneau mis à jour.');
    }

    /**
     * Après une écriture, renvoie vers le détail du créneau — sauf si l'auteur
     * n'a pas le droit de le voir (show() étant scopé par rôle), auquel cas il
     * tomberait sur un 404 donnant l'illusion d'un échec alors que
     * l'enregistrement a réussi. Cas concret : un Professeur qui choisit dans
     * le formulaire le cours de section d'un collègue (create()/edit() les
     * proposent tous). On le renvoie alors vers la liste, avec le même flash
     * de succès.
     *
     * Pas de branche `as_parent` ici : ces routes sont gardées par `can-manage`,
     * qu'un Parent ne franchit jamais.
     */
    private function redirectAfterWrite(Request $request, Schedule $schedule, string $message): \Illuminate\Http\RedirectResponse
    {
        $schoolId = session('active_school_id');
        $user = $request->user();

        $allowedSectionUserIds = $this->resolveAllowedSectionUserIds(
            $user->scopedUserSchoolRole($schoolId ?? 0),
            $user->activeRoleAt($schoolId ?? 0),
        );

        $canSee = $allowedSectionUserIds === null
            || $allowedSectionUserIds->contains($schedule->sectionCourse?->section_user_id);

        $target = $canSee
            ? redirect()->route('schedules.show', $schedule)
            : redirect()->route('schedules.index');

        return $target->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('flash', ['type' => 'success', 'message' => 'Créneau supprimé.']);
    }

    /**
     * `section_course_id` has no direct `school_id` column — it's reached via
     * SectionCourse → course → school_id. `Rule::exists` can't express that
     * relation, so use a closure rule instead.
     */
    private function sectionCourseBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! SectionCourse::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Ce cours de section n\'appartient pas à votre établissement.');
            }
        };
    }

    /**
     * `subject_id` has no direct `school_id` column — it's reached via
     * Subject → course → school_id. `Rule::exists` can't express that
     * relation, so use a closure rule instead.
     */
    private function subjectBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Cette matière n\'appartient pas à votre établissement.');
            }
        };
    }

    /**
     * Rôles voyant l'horaire de toute l'école : union de « écrit largement le
     * contenu académique » (Power User, Secrétariat) et « lit toute l'école »
     * (Directeur). Diverge volontairement de DashboardController::MANAGE_ROLES,
     * qui omet Secrétariat : celui-ci écrit les horaires via `can-manage`
     * (EnsureCanManage::MANAGE_ROLES) et doit donc les voir tous, alors qu'il
     * n'a pas d'équivalent côté widget lecture seule du dashboard. Professeur
     * en est exclu à dessein — le limiter à ses propres sections est tout
     * l'objet de ce scoping. Administrateur aussi (cf. CLAUDE.md : pas
     * d'autorité sur le contenu académique d'une école en particulier).
     */
    private const MANAGE_ROLES = ['Power User', 'Secrétariat', 'Directeur'];

    /**
     * Ensemble des section_user_id auxquels $currentRole a droit de regard,
     * ou null si aucune restriction ne s'applique (rôle de gestion : toute
     * l'école). Même logique que DashboardController::weekSchedule() — à
     * garder synchronisée si l'une des deux évolue.
     */
    private function resolveAllowedSectionUserIds(?UserSchoolRole $usr, ?string $currentRole): ?\Illuminate\Support\Collection
    {
        if (in_array($currentRole, self::MANAGE_ROLES, true)) {
            return null;
        }

        if (! $usr) {
            return collect();
        }

        if ($currentRole === 'Professeur') {
            return SectionUserSchoolRole::where('user_school_role_id', $usr->id)->pluck('id');
        }

        // Élève (et tout rôle sans portée de gestion connue, y compris l'enfant
        // d'un Parent) : sections où $usr est inscrit, puis tous les
        // section_user_id de ces sections (profs compris), pour que les
        // créneaux des cours qu'il suit apparaissent.
        $sectionIds = SectionUserSchoolRole::where('user_school_role_id', $usr->id)->pluck('section_id');

        return SectionUserSchoolRole::whereIn('section_id', $sectionIds)->pluck('id');
    }
}
