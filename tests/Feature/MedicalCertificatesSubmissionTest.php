<?php

use App\Models\MedicalCertificate;
use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\MedicalCertificateRejectedNotification;
use App\Notifications\MedicalCertificateSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function makeMcsSchool(): School
{
    return School::create(['name' => 'École MCS '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeMcsRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeMcsUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeMcsStudent(School $school): array
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $studentUsr = makeMcsUsr($school, makeMcsRole('ELEVE', 'Élève'));
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return [$studentUsr, $sectionUser];
}

function linkMcsParent(UserSchoolRole $student, User $parent, School $school): void
{
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeMcsRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('a student can submit a certificate with an attachment, it stays pending', function () {
    Storage::fake('local');
    Notification::fake();
    $school = makeMcsSchool();
    [$studentUsr, $sectionUser] = makeMcsStudent($school);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates/submit', [
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'attachment' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $certificate = MedicalCertificate::where('section_user_id', $sectionUser->id)->first();
    expect($certificate->status)->toBe('P');
    expect($certificate->submitted_by)->toBe($studentUsr->user->id);
});

test('submitting without an attachment is rejected', function () {
    $school = makeMcsSchool();
    [$studentUsr] = makeMcsStudent($school);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates/submit', [
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
        ])
        ->assertSessionHasErrors('attachment');

    expect(MedicalCertificate::count())->toBe(0);
});

test('submitting a certificate notifies Secrétariat and Power User of the school', function () {
    Storage::fake('local');
    Notification::fake();
    $school = makeMcsSchool();
    [$studentUsr] = makeMcsStudent($school);
    $secretariat = makeMcsUsr($school, makeMcsRole('SEC', 'Secrétariat'))->user;
    $powerUser = makeMcsUsr($school, makeMcsRole('POWER', 'Power User'))->user;
    $prof = makeMcsUsr($school, makeMcsRole('PROF', 'Professeur'))->user;

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates/submit', [
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'attachment' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ]);

    Notification::assertSentTo($secretariat, MedicalCertificateSubmittedNotification::class);
    Notification::assertSentTo($powerUser, MedicalCertificateSubmittedNotification::class);
    Notification::assertNotSentTo($prof, MedicalCertificateSubmittedNotification::class);
});

test('a parent can submit a certificate for their linked child', function () {
    Storage::fake('local');
    $school = makeMcsSchool();
    [$studentUsr, $sectionUser] = makeMcsStudent($school);
    $parent = User::factory()->create();
    linkMcsParent($studentUsr, $parent, $school);

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates/submit', [
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'attachment' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    expect(MedicalCertificate::where('section_user_id', $sectionUser->id)->exists())->toBeTrue();
});

test('Secrétariat approving a pending certificate activates it and triggers reconciliation', function () {
    Storage::fake('local');
    Notification::fake();
    $school = makeMcsSchool();
    [$studentUsr, $sectionUser] = makeMcsStudent($school);
    $secretariat = makeMcsUsr($school, makeMcsRole('SEC', 'Secrétariat'))->user;

    $certificate = MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $sectionUser->id,
        'starts_at' => '2026-01-05', 'ends_at' => '2026-01-08',
        'status' => 'P', 'submitted_by' => $studentUsr->user->id, 'is_active' => true, 'created_by' => $studentUsr->user->id,
    ]);

    $this->actingAs($secretariat)
        ->withSession(['active_school_id' => $school->id])
        ->post("/medical-certificates/{$certificate->id}/approve")
        ->assertRedirect();

    $certificate->refresh();
    expect($certificate->status)->toBe('A');
    expect($certificate->reviewed_by)->toBe($secretariat->id);
});

test('Power User rejecting a pending certificate notifies the submitter and never activates it', function () {
    Storage::fake('local');
    Notification::fake();
    $school = makeMcsSchool();
    [$studentUsr, $sectionUser] = makeMcsStudent($school);
    $powerUser = makeMcsUsr($school, makeMcsRole('POWER', 'Power User'))->user;

    $certificate = MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $sectionUser->id,
        'starts_at' => '2026-01-05', 'ends_at' => '2026-01-08',
        'status' => 'P', 'submitted_by' => $studentUsr->user->id, 'is_active' => true, 'created_by' => $studentUsr->user->id,
    ]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/medical-certificates/{$certificate->id}/reject", ['rejection_reason' => 'Document illisible'])
        ->assertRedirect();

    $certificate->refresh();
    expect($certificate->status)->toBe('R');
    expect($certificate->rejection_reason)->toBe('Document illisible');
    Notification::assertSentTo($studentUsr->user, MedicalCertificateRejectedNotification::class);
});

test('Professeur cannot approve or reject a certificate', function () {
    $school = makeMcsSchool();
    [$studentUsr, $sectionUser] = makeMcsStudent($school);
    $prof = makeMcsUsr($school, makeMcsRole('PROF', 'Professeur'))->user;

    $certificate = MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $sectionUser->id,
        'starts_at' => '2026-01-05', 'ends_at' => '2026-01-08',
        'status' => 'P', 'submitted_by' => $studentUsr->user->id, 'is_active' => true, 'created_by' => $studentUsr->user->id,
    ]);

    $this->actingAs($prof)
        ->withSession(['active_school_id' => $school->id])
        ->post("/medical-certificates/{$certificate->id}/approve")
        ->assertForbidden();

    expect($certificate->fresh()->status)->toBe('P');
});

test('a student cannot submit a certificate for another student', function () {
    Storage::fake('local');
    $school = makeMcsSchool();
    [, $sectionUser] = makeMcsStudent($school);
    [$otherStudentUsr] = makeMcsStudent($school);

    // otherStudentUsr soumet — le certificat doit être rattaché à SON propre
    // section_user_id, jamais à celui passé (il n'y en a d'ailleurs pas dans
    // le payload : submit() ne prend jamais section_user_id du client).
    $this->actingAs($otherStudentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates/submit', [
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'attachment' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ]);

    $certificate = MedicalCertificate::latest('id')->first();
    expect($certificate->section_user_id)->not->toBe($sectionUser->id);
});
