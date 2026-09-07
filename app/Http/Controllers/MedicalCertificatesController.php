<?php

namespace App\Http\Controllers;

use App\Concerns\ReconcilesAttendanceCertificates;
use App\Models\MedicalCertificate;
use App\Models\SectionUserSchoolRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalCertificatesController extends Controller
{
    use ReconcilesAttendanceCertificates;

    /** Aligné sur la décision de spec : Directeur/Professeur en sont exclus, contrairement à can-manage. */
    private const CERTIFICATE_STAFF_ROLES = ['Secrétariat', 'Power User'];

    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');

        $query = MedicalCertificate::where('school_id', $schoolId)
            ->with(['sectionUser.userschoolrole.user'])
            ->orderByDesc('created_at');

        if (! $this->isCertificateStaff($request, $schoolId)) {
            // `section_user_id` référence section_users.id (SectionUserSchoolRole),
            // alors que scopedUserSchoolRole() renvoie un UserSchoolRole (PK
            // distincte, users_schools_roles.id) : on ne peut pas comparer les deux
            // id directement, il faut traverser la relation — même pattern que
            // GradesController::index()/downloadAttachment().
            $scopedUsr = $request->user()->scopedUserSchoolRole($schoolId);
            $query->when(
                $scopedUsr,
                fn ($q) => $q->whereHas('sectionUser.userschoolrole', fn ($q2) => $q2->where('id', $scopedUsr->id)),
                fn ($q) => $q->whereRaw('1 = 0')
            );
        }

        return Inertia::render('power-user/web/MedicalCertificates/Index', [
            'certificates' => $query->get()->map(fn (MedicalCertificate $c) => [
                'id' => $c->id,
                'student_name' => $c->sectionUser?->userschoolrole?->user
                    ? "{$c->sectionUser->userschoolrole->user->lastname} {$c->sectionUser->userschoolrole->user->firstname}"
                    : '—',
                'starts_at' => $c->starts_at->toDateString(),
                'ends_at' => $c->ends_at->toDateString(),
                'reason' => $c->reason,
                'status' => $c->status,
                'has_attachment' => $c->has_attachment,
                'rejection_reason' => $c->rejection_reason,
            ]),
            'is_certificate_staff' => $this->isCertificateStaff($request, $schoolId),
        ]);
    }

    public function create(Request $request): Response
    {
        $schoolId = session('active_school_id');
        abort_unless($this->isCertificateStaff($request, $schoolId), 403);

        return Inertia::render('power-user/web/MedicalCertificates/Create', [
            'students' => SectionUserSchoolRole::where('is_active', true)
                ->whereHas('userschoolrole', fn ($q) => $q->where('school_id', $schoolId)
                    ->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE')))
                ->with(['userschoolrole.user'])
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        abort_unless($this->isCertificateStaff($request, $schoolId), 403);

        $data = $request->validate([
            'section_user_id' => ['required', 'integer', $this->sectionUserBelongsToSchool($schoolId)],
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $data['school_id'] = $schoolId;
        $data['status'] = 'A';
        $data['submitted_by'] = $request->user()->id;
        $data['reviewed_by'] = $request->user()->id;
        $data['reviewed_at'] = now();
        $data['is_active'] = true;
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('medical-certificates', 'local');
            $data['attachment_original_name'] = $request->file('attachment')->getClientOriginalName();
        }

        $certificate = MedicalCertificate::create($data);

        $this->reconcileCertificate($certificate);

        return redirect()->route('medical-certificates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Certificat enregistré et présences justifiées.']);
    }

    public function downloadAttachment(Request $request, MedicalCertificate $medicalCertificate): StreamedResponse
    {
        $schoolId = session('active_school_id');
        abort_unless($medicalCertificate->attachment_path && Storage::disk('local')->exists($medicalCertificate->attachment_path), 404);

        if (! $this->isCertificateStaff($request, $schoolId)) {
            // Même remarque que index() : comparaison via la relation, pas par id direct.
            $medicalCertificate->loadMissing('sectionUser.userschoolrole');
            $scopedUsr = $request->user()->scopedUserSchoolRole($schoolId);
            abort_unless($scopedUsr && $medicalCertificate->sectionUser?->userschoolrole?->id === $scopedUsr->id, 403);
        }

        return Storage::disk('local')->download($medicalCertificate->attachment_path, $medicalCertificate->attachment_original_name);
    }

    /**
     * `section_user_id` has no direct `school_id` column — atteint via
     * SectionUserSchoolRole → userschoolrole → school_id (même pattern que
     * GradesController).
     */
    private function sectionUserBelongsToSchool(?int $schoolId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($schoolId) {
            if (! SectionUserSchoolRole::whereHas('userschoolrole', fn ($q) => $q->where('school_id', $schoolId))->whereKey($value)->exists()) {
                $fail('Cet élève n\'appartient pas à votre établissement.');
            }
        };
    }

    private function isCertificateStaff(Request $request, ?int $schoolId): bool
    {
        if (! $schoolId) {
            return false;
        }

        $role = $request->user()->activeRoleAt($schoolId);

        return in_array($role, self::CERTIFICATE_STAFF_ROLES, true);
    }
}
