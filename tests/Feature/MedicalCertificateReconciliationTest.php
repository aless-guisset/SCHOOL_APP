<?php

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\MedicalCertificate;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\UserSchoolRole;
use Carbon\Carbon;

uses(App\Concerns\ReconcilesAttendanceCertificates::class);

function makeCertSchool(): School
{
    return School::create(['name' => 'École Cert '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeCertRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeCertUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Section + cours + section_course + schedule + timesheet à la date donnée + élève inscrit. */
function makeCertSession(School $school, UserSchoolRole $teacherUsr, string $date): array
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherSectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $teacherUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $teacherSectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $schedule = Schedule::create(['section_course_id' => $sectionCourse->id, 'name' => 'Séance', 'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => $date, 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $studentUsr = makeCertUsr($school, makeCertRole('ELEVE', 'Élève'));
    $studentSectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return compact('timesheet', 'studentSectionUser');
}

function makeCertificate(School $school, SectionUserSchoolRole $student, string $startsAt, string $endsAt, string $status = 'A'): MedicalCertificate
{
    $submitter = User::factory()->create();

    return MedicalCertificate::create([
        'school_id' => $school->id, 'section_user_id' => $student->id,
        'starts_at' => $startsAt, 'ends_at' => $endsAt,
        'status' => $status, 'submitted_by' => $submitter->id,
        'is_active' => true, 'created_by' => $submitter->id,
    ]);
}

test('activating a certificate retroactively justifies existing absences in its date range', function () {
    $school = makeCertSchool();
    $teacherUsr = makeCertUsr($school, makeCertRole('PROF', 'Professeur'));
    $session = makeCertSession($school, $teacherUsr, '2026-01-06');

    $attendance = Attendance::create([
        'timesheet_id' => $session['timesheet']->id, 'section_user_id' => $session['studentSectionUser']->id,
        'presence_status' => 'A', 'justification_status' => 'I',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $certificate = makeCertificate($school, $session['studentSectionUser'], '2026-01-05', '2026-01-08');

    $this->reconcileCertificate($certificate);

    $attendance->refresh();
    expect($attendance->justification_status)->toBe('J');
    expect($attendance->medical_certificate_id)->toBe($certificate->id);
});

test('reconciliation is idempotent — replaying it does not error or change anything further', function () {
    $school = makeCertSchool();
    $teacherUsr = makeCertUsr($school, makeCertRole('PROF', 'Professeur'));
    $session = makeCertSession($school, $teacherUsr, '2026-01-06');

    Attendance::create([
        'timesheet_id' => $session['timesheet']->id, 'section_user_id' => $session['studentSectionUser']->id,
        'presence_status' => 'R', 'justification_status' => 'I',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $certificate = makeCertificate($school, $session['studentSectionUser'], '2026-01-05', '2026-01-08');

    $this->reconcileCertificate($certificate);
    $this->reconcileCertificate($certificate);

    expect(Attendance::where('section_user_id', $session['studentSectionUser']->id)->first()->justification_status)->toBe('J');
});

test('reconciliation never touches a present record', function () {
    $school = makeCertSchool();
    $teacherUsr = makeCertUsr($school, makeCertRole('PROF', 'Professeur'));
    $session = makeCertSession($school, $teacherUsr, '2026-01-06');

    $attendance = Attendance::create([
        'timesheet_id' => $session['timesheet']->id, 'section_user_id' => $session['studentSectionUser']->id,
        'presence_status' => 'P', 'justification_status' => null,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $certificate = makeCertificate($school, $session['studentSectionUser'], '2026-01-05', '2026-01-08');
    $this->reconcileCertificate($certificate);

    $attendance->refresh();
    expect($attendance->presence_status)->toBe('P');
    expect($attendance->justification_status)->toBeNull();
    expect($attendance->medical_certificate_id)->toBeNull();
});

test('reconciliation ignores attendances outside the certificate date range', function () {
    $school = makeCertSchool();
    $teacherUsr = makeCertUsr($school, makeCertRole('PROF', 'Professeur'));
    $session = makeCertSession($school, $teacherUsr, '2026-01-20'); // hors plage

    $attendance = Attendance::create([
        'timesheet_id' => $session['timesheet']->id, 'section_user_id' => $session['studentSectionUser']->id,
        'presence_status' => 'A', 'justification_status' => 'I',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $certificate = makeCertificate($school, $session['studentSectionUser'], '2026-01-05', '2026-01-08');
    $this->reconcileCertificate($certificate);

    $attendance->refresh();
    expect($attendance->justification_status)->toBe('I');
    expect($attendance->medical_certificate_id)->toBeNull();
});

test('activeCertificateCovering finds an active certificate covering a given date', function () {
    $school = makeCertSchool();
    $teacherUsr = makeCertUsr($school, makeCertRole('PROF', 'Professeur'));
    $session = makeCertSession($school, $teacherUsr, '2026-01-06');

    $certificate = makeCertificate($school, $session['studentSectionUser'], '2026-01-05', '2026-01-08');

    $found = $this->activeCertificateCovering($session['studentSectionUser']->id, '2026-01-06');
    expect($found?->id)->toBe($certificate->id);

    expect($this->activeCertificateCovering($session['studentSectionUser']->id, '2026-01-20'))->toBeNull();
});

test('activeCertificateCovering ignores a pending or rejected certificate', function () {
    $school = makeCertSchool();
    $teacherUsr = makeCertUsr($school, makeCertRole('PROF', 'Professeur'));
    $session = makeCertSession($school, $teacherUsr, '2026-01-06');

    makeCertificate($school, $session['studentSectionUser'], '2026-01-05', '2026-01-08', 'P');
    makeCertificate($school, $session['studentSectionUser'], '2026-01-05', '2026-01-08', 'R');

    expect($this->activeCertificateCovering($session['studentSectionUser']->id, '2026-01-06'))->toBeNull();
});

test('storing a new absence within an active certificate range is forced to justified regardless of client input', function () {
    $school = makeCertSchool();
    $teacherUsr = makeCertUsr($school, makeCertRole('PROF', 'Professeur'));
    $session = makeCertSession($school, $teacherUsr, Carbon::now()->subDay()->toDateString());
    makeCertificate($school, $session['studentSectionUser'], Carbon::now()->subWeek()->toDateString(), Carbon::now()->addWeek()->toDateString());

    $powerUser = makeCertUsr($school, makeCertRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$session['timesheet']->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $session['studentSectionUser']->id, 'presence_status' => 'A', 'justification_status' => 'I', 'note' => null],
            ],
        ])
        ->assertRedirect();

    $attendance = Attendance::where('section_user_id', $session['studentSectionUser']->id)->first();
    expect($attendance->justification_status)->toBe('J');
    expect($attendance->medical_certificate_id)->not->toBeNull();
});
