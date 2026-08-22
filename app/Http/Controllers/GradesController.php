<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\UserSchoolRole;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GradesController extends Controller
{
    /** Aligné sur DashboardController::MANAGE_ROLES et useSchool.ts::canManage. */
    private const MANAGE_ROLES = ['Administrateur', 'Power User', 'Directeur'];

    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');

        $query = Grade::whereHas('subject.course', fn ($q) => $q->where('school_id', $schoolId))
            ->with(['sectionUser.userschoolrole.user', 'subject'])
            ->orderByDesc('created_at');

        if (! $this->canManage($request, $schoolId)) {
            // Rôle sans portée de gestion (Élève, Professeur…) : uniquement ses
            // propres notes, jamais celles de toute l'école.
            $query->whereHas('sectionUser.userschoolrole', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        return Inertia::render('power-user/web/Grades/Index', [
            'grades' => $query->get(),
        ]);
    }

    public function create(): Response
    {
        $schoolId = session('active_school_id');

        return Inertia::render('power-user/web/Grades/Create', [
            'students' => SectionUserSchoolRole::where('is_active', true)
                ->whereHas('userschoolrole', fn ($q) => $q->where('school_id', $schoolId)
                    ->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
                ->with(['userschoolrole.user', 'section'])
                ->get(),
            'subjects' => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');

        $data = $request->validate([
            'section_user_id' => ['required', 'integer', $this->sectionUserBelongsToSchool($schoolId)],
            'subject_id' => ['required', 'integer', $this->subjectBelongsToSchool($schoolId)],
            'period' => 'required|string|max:50',
            'grade' => 'required|numeric|min:0|max:20',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;

        Grade::updateOrCreate(
            ['section_user_id' => $data['section_user_id'], 'subject_id' => $data['subject_id'], 'period' => $data['period']],
            ['grade' => $data['grade'], 'status' => 'A', 'is_active' => true, 'updated_by' => $request->user()->id, 'created_by' => $request->user()->id]
        );

        return redirect()->route('grades.index')
            ->with('flash', ['type' => 'success', 'message' => 'Note enregistrée.']);
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        $grade->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $grade->delete();

        return redirect()->route('grades.index')
            ->with('flash', ['type' => 'success', 'message' => 'Note supprimée.']);
    }

    public function bulletin(Request $request, SectionUserSchoolRole $sectionUser): HttpResponse
    {
        $schoolId = session('active_school_id');

        abort_unless($sectionUser->userschoolrole?->school_id == $schoolId, 404);

        // Un rôle sans portée de gestion ne peut télécharger que son propre bulletin.
        if (! $this->canManage($request, $schoolId)) {
            abort_unless($sectionUser->userschoolrole?->user_id === $request->user()->id, 403);
        }

        $grades = Grade::where('section_user_id', $sectionUser->id)
            ->where('is_active', true)
            ->with('subject')
            ->orderBy('period')
            ->get();

        $sectionUser->load('userschoolrole.user', 'section');

        $pdf = Pdf::loadView('pdf.bulletin', [
            'student' => $sectionUser,
            'grades' => $grades,
        ]);

        $studentName = $sectionUser->userschoolrole?->user
            ? "{$sectionUser->userschoolrole->user->lastname}-{$sectionUser->userschoolrole->user->firstname}"
            : "eleve-{$sectionUser->id}";

        return $pdf->download("bulletin-{$studentName}.pdf");
    }

    /**
     * `section_user_id` has no direct `school_id` column — atteint via
     * SectionUserSchoolRole → userschoolrole → school_id.
     */
    private function sectionUserBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! SectionUserSchoolRole::whereHas('userschoolrole', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Cet élève n\'appartient pas à votre établissement.');
            }
        };
    }

    /**
     * `subject_id` has no direct `school_id` column — atteint via
     * Subject → course → school_id.
     */
    private function subjectBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Cette matière n\'appartient pas à votre établissement.');
            }
        };
    }

    private function canManage(Request $request, ?int $schoolId): bool
    {
        $role = UserSchoolRole::with('role')
            ->where('user_id', $request->user()->id)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->first()
            ?->role
            ?->name;

        return in_array($role, self::MANAGE_ROLES, true);
    }
}
