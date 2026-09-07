<?php

namespace App\Http\Controllers;

use App\Concerns\ReconcilesAttendanceCertificates;
use App\Models\MedicalCertificate;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Notifications\MedicalCertificateRejectedNotification;
use App\Notifications\MedicalCertificateSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalCertificatesController extends Controller
{
    use ReconcilesAttendanceCertificates;

    /** Aligné sur la décision de spec : Directeur/Professeur en sont exclus, contrairement à can-manage. */
    private const CERTIFICATE_STAFF_ROLES = ['Secrétariat', 'Power User'];

    /** Soumission élève/parent : seuls ces rôles PROPRES à l'appelant peuvent soumettre. */
    private const CERTIFICATE_SUBMITTER_ROLES = ['Élève', 'Parent'];

    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $asParent = $request->boolean('as_parent');

        $query = MedicalCertificate::where('school_id', $schoolId)
            ->with(['sectionUser.userschoolrole.user'])
            ->orderByDesc('created_at');

        $viewingChild = null;

        if ($asParent || ! $this->canViewAllCertificates($request, $schoolId)) {
            // `section_user_id` référence section_users.id (SectionUserSchoolRole),
            // alors que scopedUserSchoolRole()/parentLinkedStudent() renvoient un
            // UserSchoolRole (PK distincte, users_schools_roles.id) : on ne peut
            // pas comparer les deux id directement, il faut traverser la relation
            // — même pattern que GradesController::index()/downloadAttachment().
            $scopedUsr = $asParent
                ? $request->user()->parentLinkedStudent($schoolId)
                : $request->user()->scopedUserSchoolRole($schoolId);

            if ($asParent && $scopedUsr?->user) {
                $viewingChild = "{$scopedUsr->user->firstname} {$scopedUsr->user->lastname}";
            }

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
            'is_certificate_staff' => ! $asParent && $this->isCertificateStaff($request, $schoolId),
            'is_certificate_submitter' => $this->isCertificateSubmitter($request, $schoolId, $asParent),
            'viewing_child' => $viewingChild,
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

    /**
     * Formulaire de soumission élève/parent. Le rôle propre de l'appelant
     * (pas celui de scopedUserSchoolRole(), qui pour un Parent est déjà celui
     * de l'enfant) doit être Élève ou Parent — un Professeur ayant sa propre
     * ligne section_users ne doit pas pouvoir soumettre.
     */
    public function submitPage(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $asParent = $request->boolean('as_parent');
        abort_unless($this->isCertificateSubmitter($request, $schoolId, $asParent), 403);

        return Inertia::render('power-user/web/MedicalCertificates/Submit', [
            'as_parent' => $asParent,
        ]);
    }

    /** Soumission par l'élève lui-même, ou par son parent pour l'enfant lié. */
    public function submit(Request $request): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $asParent = $request->boolean('as_parent');
        abort_unless($this->isCertificateSubmitter($request, $schoolId, $asParent), 403);

        // `section_user_id` référence section_users.id (SectionUserSchoolRole),
        // alors que scopedUserSchoolRole()/parentLinkedStudent() renvoient un
        // UserSchoolRole (PK distincte, users_schools_roles.id) : on résout
        // d'abord la ligne UserSchoolRole de l'appelant (l'élève lui-même, son
        // enfant si Parent sans ?as_parent=1, ou explicitement l'enfant lié si
        // ?as_parent=1 pour un double rôle staff+parent), puis on retrouve SA
        // propre inscription active (section_users) via cette ligne — jamais
        // l'id UserSchoolRole directement. Même pattern que index()/downloadAttachment().
        $scopedUsr = $asParent
            ? $request->user()->parentLinkedStudent($schoolId)
            : $request->user()->scopedUserSchoolRole($schoolId);
        abort_unless($scopedUsr, 403);

        $sectionUser = SectionUserSchoolRole::where('user_school_role_id', $scopedUsr->id)
            ->where('is_active', true)
            ->first();
        abort_unless($sectionUser, 403);

        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $certificate = MedicalCertificate::create([
            'school_id' => $schoolId,
            'section_user_id' => $sectionUser->id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $request->file('attachment')->store('medical-certificates', 'local'),
            'attachment_original_name' => $request->file('attachment')->getClientOriginalName(),
            'status' => 'P',
            'submitted_by' => $request->user()->id,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $staff = User::whereHas('schoolRoles', fn ($q) => $q
            ->where('school_id', $schoolId)->where('status', 'A')->where('is_active', true)
            ->whereHas('role', fn ($q2) => $q2->whereIn('name', self::CERTIFICATE_STAFF_ROLES)))
            ->get();

        Notification::send($staff, new MedicalCertificateSubmittedNotification($certificate));

        return redirect()->route('medical-certificates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Certificat soumis, en attente de validation.']);
    }

    public function approve(Request $request, MedicalCertificate $medicalCertificate): RedirectResponse
    {
        $schoolId = session('active_school_id');
        abort_unless($this->isCertificateStaff($request, $schoolId), 403);
        abort_if($medicalCertificate->status !== 'P', 422, 'Ce certificat n\'est plus en attente.');

        $medicalCertificate->update([
            'status' => 'A',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        $this->reconcileCertificate($medicalCertificate);

        return redirect()->route('medical-certificates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Certificat approuvé, présences justifiées.']);
    }

    public function reject(Request $request, MedicalCertificate $medicalCertificate): RedirectResponse
    {
        $schoolId = session('active_school_id');
        abort_unless($this->isCertificateStaff($request, $schoolId), 403);
        abort_if($medicalCertificate->status !== 'P', 422, 'Ce certificat n\'est plus en attente.');

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $medicalCertificate->update([
            'status' => 'R',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $submitter = User::find($medicalCertificate->submitted_by);
        if ($submitter) {
            Notification::send($submitter, new MedicalCertificateRejectedNotification($medicalCertificate));
        }

        return redirect()->route('medical-certificates.index')
            ->with('flash', ['type' => 'warning', 'message' => 'Certificat rejeté.']);
    }

    public function downloadAttachment(Request $request, MedicalCertificate $medicalCertificate): StreamedResponse
    {
        $schoolId = session('active_school_id');
        $asParent = $request->boolean('as_parent');
        abort_unless($medicalCertificate->attachment_path && Storage::disk('local')->exists($medicalCertificate->attachment_path), 404);

        if ($asParent || ! $this->canViewAllCertificates($request, $schoolId)) {
            // Même remarque que index() : comparaison via la relation, pas par id direct.
            $medicalCertificate->loadMissing('sectionUser.userschoolrole');
            $scopedUsr = $asParent
                ? $request->user()->parentLinkedStudent($schoolId)
                : $request->user()->scopedUserSchoolRole($schoolId);
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

    /**
     * Secrétariat/Power User (gestion) + Directeur (lecture seule, jamais
     * create/approve/reject — is_certificate_staff reste réservé aux deux
     * premiers). Utilisé uniquement pour élargir la PORTÉE de ce qui est vu
     * dans index()/downloadAttachment(), jamais pour autoriser une action
     * d'écriture.
     */
    private function canViewAllCertificates(Request $request, ?int $schoolId): bool
    {
        if (! $schoolId) {
            return false;
        }

        $role = $request->user()->activeRoleAt($schoolId);

        return in_array($role, [...self::CERTIFICATE_STAFF_ROLES, 'Directeur'], true);
    }

    /**
     * Vrai si le rôle PROPRE de l'appelant à cette école est Élève ou Parent.
     * Contrairement à scopedUserSchoolRole() (qui, pour un Parent, résout déjà
     * vers la ligne UserSchoolRole de l'enfant, donc toujours ELEVE), ceci lit
     * le rôle réel de l'appelant — nécessaire car un Professeur possède aussi
     * une ligne section_users (pour sa propre affectation d'enseignement) et
     * passerait sinon le seul check abort_unless($scopedUsr, 403).
     */
    private function isCertificateSubmitter(Request $request, ?int $schoolId, bool $asParent = false): bool
    {
        if (! $schoolId) {
            return false;
        }

        if ($asParent) {
            return $request->user()->parentLinkedStudent($schoolId) !== null;
        }

        $role = $request->user()->activeRoleAt($schoolId);

        return in_array($role, self::CERTIFICATE_SUBMITTER_ROLES, true);
    }
}
