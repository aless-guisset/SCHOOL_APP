<?php

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
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

function makeAttendanceSchool(): School
{
    return School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeAttendanceRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeAttendanceUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeAttendanceSection(School $school, string $name = 'Classe A'): Section
{
    return Section::create([
        'school_id' => $school->id, 'name' => $name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function enrollAttendanceStudent(Section $section, Role $eleveRole, School $school): SectionUserSchoolRole
{
    $studentUsr = makeAttendanceUsr($school, $eleveRole);

    return SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Crée course + section_user (prof) + section_course + schedule + timesheet. Retourne le Timesheet. */
function makeAttendanceSessionFor(School $school, Section $section, UserSchoolRole $teacherUsr): Timesheet
{
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $teacherSectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $teacherUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionCourse = SectionCourse::create([
        'section_user_id' => $teacherSectionUser->id, 'course_id' => $course->id,
        'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'Maths '.$section->name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $schedule = Schedule::create([
        'section_course_id' => $sectionCourse->id, 'name' => 'Lundi',
        'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-08-24', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('attendance belongs to a timesheet and a section user', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, makeAttendanceRole('ELEVE', 'Élève'), $school);

    $attendance = Attendance::create([
        'timesheet_id' => $timesheet->id, 'section_user_id' => $student->id,
        'is_present' => false, 'note' => 'Certificat médical reçu',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($attendance->timesheet->id)->toBe($timesheet->id);
    expect($attendance->sectionUser->id)->toBe($student->id);
    expect($attendance->is_present)->toBeFalse();
});

test('roster only includes students of the session section, defaulting to present', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $otherSection = makeAttendanceSection($school, 'Classe B');
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);

    $student = enrollAttendanceStudent($section, $eleveRole, $school);
    enrollAttendanceStudent($otherSection, $eleveRole, $school); // élève d'une autre section — exclu

    $powerUserRole = makeAttendanceRole('POWER', 'Power User');
    $powerUser = makeAttendanceUsr($school, $powerUserRole)->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/timesheets/{$timesheet->id}")
        ->assertInertia(fn ($page) => $page
            ->component('power-user/web/Timesheets/Show')
            ->has('roster', 1)
            ->where('roster.0.section_user_id', $student->id)
            ->where('roster.0.is_present', true)
            ->where('roster.0.note', null)
        );
});

test('roster reflects an already-recorded absence', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    Attendance::create([
        'timesheet_id' => $timesheet->id, 'section_user_id' => $student->id,
        'is_present' => false, 'note' => 'Certificat médical reçu',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/timesheets/{$timesheet->id}")
        ->assertInertia(fn ($page) => $page
            ->where('roster.0.is_present', false)
            ->where('roster.0.note', 'Certificat médical reçu')
        );
});

test('storing attendance creates one row per student and upserts on resubmit', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student1 = enrollAttendanceStudent($section, $eleveRole, $school);
    $student2 = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student1->id, 'is_present' => true,  'note' => null],
                ['section_user_id' => $student2->id, 'is_present' => false, 'note' => 'Absent non justifié'],
            ],
        ])
        ->assertRedirect();

    expect(Attendance::count())->toBe(2);
    expect(Attendance::where('section_user_id', $student2->id)->first()->is_present)->toBeFalse();
    expect(Attendance::where('section_user_id', $student2->id)->first()->note)->toBe('Absent non justifié');

    // Ré-envoi : met à jour, ne duplique pas
    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student1->id, 'is_present' => false, 'note' => 'Rentré chez lui malade'],
                ['section_user_id' => $student2->id, 'is_present' => true,  'note' => null],
            ],
        ]);

    expect(Attendance::count())->toBe(2);
    expect(Attendance::where('section_user_id', $student1->id)->first()->is_present)->toBeFalse();
    expect(Attendance::where('section_user_id', $student2->id)->first()->is_present)->toBeTrue();
});

test('storing attendance for a student outside the session section is rejected', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $otherSection = makeAttendanceSection($school, 'Classe B');
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $outsideStudent = enrollAttendanceStudent($otherSection, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $outsideStudent->id, 'is_present' => false, 'note' => null],
            ],
        ])
        ->assertSessionHasErrors('attendances.0.section_user_id');

    expect(Attendance::count())->toBe(0);
});

test("storing attendance for the teacher's own section_user_id is rejected", function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);

    $teacherSectionUser = SectionUserSchoolRole::where('section_id', $section->id)
        ->where('user_school_role_id', $teacherUsr->id)
        ->first();

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $teacherSectionUser->id, 'is_present' => false, 'note' => null],
            ],
        ])
        ->assertSessionHasErrors('attendances.0.section_user_id');

    expect(Attendance::count())->toBe(0);
});

test('roster is an empty array when no students are enrolled in the section', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/timesheets/{$timesheet->id}")
        ->assertInertia(fn ($page) => $page
            ->component('power-user/web/Timesheets/Show')
            ->has('roster', 0)
        );
});

test('storing attendance on a timesheet belonging to another school is rejected', function () {
    $schoolA = makeAttendanceSchool();
    $schoolB = makeAttendanceSchool();

    $sectionA = makeAttendanceSection($schoolA);
    $teacherUsrA = makeAttendanceUsr($schoolA, makeAttendanceRole('PROF', 'Professeur'));
    makeAttendanceSessionFor($schoolA, $sectionA, $teacherUsrA);

    $sectionB = makeAttendanceSection($schoolB, 'Classe B');
    $teacherUsrB = makeAttendanceUsr($schoolB, makeAttendanceRole('PROF', 'Professeur'));
    $timesheetB = makeAttendanceSessionFor($schoolB, $sectionB, $teacherUsrB);

    $powerUserA = makeAttendanceUsr($schoolA, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post("/timesheets/{$timesheetB->id}/attendance", [
            'attendances' => [],
        ])
        ->assertNotFound();

    expect(Attendance::count())->toBe(0);
});

test('created_by is preserved and updated_by changes when a different user resubmits', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerRole = makeAttendanceRole('POWER', 'Power User');
    $powerUserA = makeAttendanceUsr($school, $powerRole)->user;
    $powerUserB = makeAttendanceUsr($school, $powerRole)->user;

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'is_present' => true, 'note' => null],
            ],
        ]);

    $attendance = Attendance::where('section_user_id', $student->id)->first();
    expect($attendance->created_by)->toBe($powerUserA->id);
    expect($attendance->updated_by)->toBe($powerUserA->id);

    $this->actingAs($powerUserB)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'is_present' => false, 'note' => 'Retard'],
            ],
        ]);

    $attendance->refresh();
    expect($attendance->created_by)->toBe($powerUserA->id);
    expect($attendance->updated_by)->toBe($powerUserB->id);
});
