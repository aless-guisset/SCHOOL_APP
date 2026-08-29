<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\UserSchoolRole;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradesController extends Controller
{
    /** Aligné sur EnsureCanManage/useSchool.ts::canManage (droit d'écriture sur le contenu académique). */
    private const MANAGE_ROLES = ['Power User', 'Secrétariat', 'Professeur'];

    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $subjectId = $request->integer('subject_id') ?: null;

        $query = Grade::whereHas('subject.course', fn ($q) => $q->where('school_id', $schoolId))
            ->with(['sectionUser.userschoolrole.user', 'subject'])
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->orderByDesc('created_at');

        if (! $this->canManage($request, $schoolId)) {
            // Rôle sans portée de gestion (Élève, Parent, Directeur…) : uniquement
            // ses propres notes (ou celles de l'enfant lié pour un Parent), jamais
            // celles de toute l'école.
            $scopedUsr = $request->user()->scopedUserSchoolRole($schoolId);
            $query->when(
                $scopedUsr,
                fn ($q) => $q->whereHas('sectionUser.userschoolrole', fn ($q2) => $q2->where('id', $scopedUsr->id)),
                fn ($q) => $q->whereRaw('1 = 0')
            );
        }

        return Inertia::render('power-user/web/Grades/Index', [
            'grades' => $query->get(),
            'subjects' => Subject::whereHas('course', fn ($q) => $q->where('school_id', $schoolId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'subject_id' => $subjectId,
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
            'max_grade' => 'required|numeric|min:1|max:1000',
            'grade' => 'required|numeric|min:0|lte:max_grade',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $payload = [
            'grade' => $data['grade'],
            'max_grade' => $data['max_grade'],
            'status' => 'A',
            'is_active' => true,
            'updated_by' => $request->user()->id,
            'created_by' => $request->user()->id,
        ];

        // Best-effort atomicity: la suppression/écriture sur disque n'est jamais
        // transactionnelle, mais englober la séquence garantit au moins que
        // l'écriture en base est atomique vis-à-vis des requêtes concurrentes.
        DB::transaction(function () use ($request, $data, $payload) {
            if ($request->hasFile('attachment')) {
                $existing = Grade::where('section_user_id', $data['section_user_id'])
                    ->where('subject_id', $data['subject_id'])
                    ->where('period', $data['period'])
                    ->first();

                if ($existing?->attachment_path) {
                    Storage::disk('local')->delete($existing->attachment_path);
                }
                $payload['attachment_path'] = $request->file('attachment')->store('grades', 'local');
                $payload['attachment_original_name'] = $request->file('attachment')->getClientOriginalName();
            }

            Grade::updateOrCreate(
                ['section_user_id' => $data['section_user_id'], 'subject_id' => $data['subject_id'], 'period' => $data['period']],
                $payload
            );
        });

        return redirect()->route('grades.index')
            ->with('flash', ['type' => 'success', 'message' => 'Note enregistrée.']);
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        if ($grade->attachment_path) {
            Storage::disk('local')->delete($grade->attachment_path);
        }

        $grade->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $grade->delete();

        return redirect()->route('grades.index')
            ->with('flash', ['type' => 'success', 'message' => 'Note supprimée.']);
    }

    public function downloadAttachment(Request $request, Grade $grade): StreamedResponse
    {
        $schoolId = session('active_school_id');
        abort_unless($grade->attachment_path && Storage::disk('local')->exists($grade->attachment_path), 404);

        if (! $this->canManage($request, $schoolId)) {
            $grade->loadMissing('sectionUser.userschoolrole');
            abort_unless($grade->sectionUser?->userschoolrole?->user_id === $request->user()->id, 403);
        }

        return Storage::disk('local')->download($grade->attachment_path, $grade->attachment_original_name);
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
        if (! $schoolId) {
            return false;
        }

        $role = $request->user()->activeRoleAt($schoolId);

        return in_array($role, self::MANAGE_ROLES, true);
    }
}
