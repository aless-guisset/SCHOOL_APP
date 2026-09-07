<?php

use App\Models\MedicalCertificate;
use App\Models\Role;
use App\Models\School;
use App\Models\SectionUserSchoolRole;
use App\Models\Section;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeMcSchool(): School
{
    return School::create(['name' => 'École MC '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeMcRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeMcUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeMcStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $studentUsr = makeMcUsr($school, makeMcRole('ELEVE', 'Élève'));

    return SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('Secrétariat can create a certificate directly, active immediately', function () {
    $school = makeMcSchool();
    $student = makeMcStudent($school);
    $secretariat = makeMcUsr($school, makeMcRole('SEC', 'Secrétariat'))->user;

    $this->actingAs($secretariat)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates', [
            'section_user_id' => $student->id,
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'reason' => 'Grippe',
        ])
        ->assertRedirect();

    $certificate = MedicalCertificate::where('section_user_id', $student->id)->first();
    expect($certificate)->not->toBeNull()
        ->and($certificate->status)->toBe('A')
        ->and($certificate->reviewed_by)->toBe($secretariat->id)
        ->and($certificate->reviewed_at)->not->toBeNull();
});

test('Power User can create a certificate directly with an attachment', function () {
    Storage::fake('local');
    $school = makeMcSchool();
    $student = makeMcStudent($school);
    $powerUser = makeMcUsr($school, makeMcRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates', [
            'section_user_id' => $student->id,
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'attachment' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $certificate = MedicalCertificate::where('section_user_id', $student->id)->first();
    expect($certificate->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertExists($certificate->attachment_path);
});

test('Professeur cannot create a certificate even though it can manage attendance', function () {
    $school = makeMcSchool();
    $student = makeMcStudent($school);
    $prof = makeMcUsr($school, makeMcRole('PROF', 'Professeur'))->user;

    $this->actingAs($prof)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates', [
            'section_user_id' => $student->id,
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
        ])
        ->assertForbidden();

    expect(MedicalCertificate::count())->toBe(0);
});

test('a directly-created certificate retroactively justifies existing absences', function () {
    $school = makeMcSchool();
    $student = makeMcStudent($school);
    $secretariat = makeMcUsr($school, makeMcRole('SEC', 'Secrétariat'))->user;

    $course = \App\Models\Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherUsr = makeMcUsr($school, makeMcRole('PROF', 'Professeur'));
    $teacherSectionUser = SectionUserSchoolRole::create(['section_id' => $student->section_id, 'user_school_role_id' => $teacherUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = \App\Models\SectionCourse::create(['section_user_id' => $teacherSectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $schedule = \App\Models\Schedule::create(['section_course_id' => $sectionCourse->id, 'name' => 'Séance', 'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $classroom = \App\Models\Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = \App\Models\Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $timesheet = \App\Models\Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id, 'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-01-06', 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    \App\Models\Attendance::create([
        'timesheet_id' => $timesheet->id, 'section_user_id' => $student->id,
        'presence_status' => 'A', 'justification_status' => 'I',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($secretariat)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates', [
            'section_user_id' => $student->id,
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
        ])
        ->assertRedirect();

    expect(\App\Models\Attendance::where('timesheet_id', $timesheet->id)->first()->justification_status)->toBe('J');
});

test('index lists certificates for the active school, scoped to staff view', function () {
    $school = makeMcSchool();
    $student = makeMcStudent($school);
    $secretariat = makeMcUsr($school, makeMcRole('SEC', 'Secrétariat'))->user;

    MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $student->id,
        'starts_at' => '2026-01-05', 'ends_at' => '2026-01-08',
        'status' => 'A', 'submitted_by' => $secretariat->id, 'reviewed_by' => $secretariat->id, 'reviewed_at' => now(),
        'is_active' => true, 'created_by' => $secretariat->id,
    ]);

    $this->actingAs($secretariat)
        ->withSession(['active_school_id' => $school->id])
        ->get('/medical-certificates')
        ->assertInertia(fn ($page) => $page
            ->component('power-user/web/MedicalCertificates/Index')
            ->has('certificates', 1)
        );
});

test('a student sees only their own certificate in the index, not another student\'s', function () {
    $school = makeMcSchool();
    $student1 = makeMcStudent($school);
    $student2 = makeMcStudent($school);
    $secretariat = makeMcUsr($school, makeMcRole('SEC', 'Secrétariat'))->user;

    MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $student1->id,
        'starts_at' => '2026-01-05', 'ends_at' => '2026-01-08',
        'status' => 'A', 'submitted_by' => $secretariat->id, 'reviewed_by' => $secretariat->id, 'reviewed_at' => now(),
        'is_active' => true, 'created_by' => $secretariat->id,
    ]);
    MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $student2->id,
        'starts_at' => '2026-01-05', 'ends_at' => '2026-01-08',
        'status' => 'A', 'submitted_by' => $secretariat->id, 'reviewed_by' => $secretariat->id, 'reviewed_at' => now(),
        'is_active' => true, 'created_by' => $secretariat->id,
    ]);

    $studentUser1 = $student1->userschoolrole->user;

    $this->actingAs($studentUser1)
        ->withSession(['active_school_id' => $school->id])
        ->get('/medical-certificates')
        ->assertInertia(fn ($page) => $page
            ->component('power-user/web/MedicalCertificates/Index')
            ->has('certificates', 1)
            ->where('certificates.0.student_name', "{$studentUser1->lastname} {$studentUser1->firstname}")
        );
});

test('a student can download their own certificate attachment but gets 403 on another student\'s', function () {
    Storage::fake('local');
    $school = makeMcSchool();
    $student1 = makeMcStudent($school);
    $student2 = makeMcStudent($school);
    $powerUser = makeMcUsr($school, makeMcRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates', [
            'section_user_id' => $student1->id,
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'attachment' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ]);

    $certificate = MedicalCertificate::where('section_user_id', $student1->id)->first();

    $studentUser1 = $student1->userschoolrole->user;
    $studentUser2 = $student2->userschoolrole->user;

    $this->actingAs($studentUser1)
        ->withSession(['active_school_id' => $school->id])
        ->get("/medical-certificates/{$certificate->id}/attachment")
        ->assertOk();

    $this->actingAs($studentUser2)
        ->withSession(['active_school_id' => $school->id])
        ->get("/medical-certificates/{$certificate->id}/attachment")
        ->assertForbidden();
});

test('Directeur sees all certificates in the school, read-only', function () {
    $school = makeMcSchool();
    $student = makeMcStudent($school);
    $directeur = makeMcUsr($school, makeMcRole('DIR', 'Directeur'))->user;
    $secretariat = makeMcUsr($school, makeMcRole('SEC', 'Secrétariat'))->user;

    MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $student->id,
        'starts_at' => '2026-01-05', 'ends_at' => '2026-01-08',
        'status' => 'A', 'submitted_by' => $secretariat->id, 'reviewed_by' => $secretariat->id, 'reviewed_at' => now(),
        'is_active' => true, 'created_by' => $secretariat->id,
    ]);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->get('/medical-certificates')
        ->assertInertia(fn ($page) => $page
            ->component('power-user/web/MedicalCertificates/Index')
            ->has('certificates', 1)
            ->where('is_certificate_staff', false)
        );
});

test('a certificate attachment can be downloaded by staff', function () {
    Storage::fake('local');
    $school = makeMcSchool();
    $student = makeMcStudent($school);
    $powerUser = makeMcUsr($school, makeMcRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/medical-certificates', [
            'section_user_id' => $student->id,
            'starts_at' => '2026-01-05',
            'ends_at' => '2026-01-08',
            'attachment' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ]);

    $certificate = MedicalCertificate::first();

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/medical-certificates/{$certificate->id}/attachment")
        ->assertOk();
});
