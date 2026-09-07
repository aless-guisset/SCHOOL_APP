<?php

use App\Models\Attendance;
use App\Models\Classroom;
use Carbon\Carbon;
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
use Illuminate\Support\Facades\Schema;

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
function makeAttendanceSessionFor(School $school, Section $section, UserSchoolRole $teacherUsr, ?string $date = null): Timesheet
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
        // Un lundi dans le passé par défaut (jamais dans le futur, quelle que
        // soit la date du jour) : AttendancesController::store() rejette
        // désormais les présences pour un cours qui n'a pas encore eu lieu.
        'date' => $date ?? Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString(),
        'hours_done' => 2,
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
        'presence_status' => 'A', 'justification_status' => 'I', 'note' => 'Absence non justifiée',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($attendance->timesheet->id)->toBe($timesheet->id);
    expect($attendance->sectionUser->id)->toBe($student->id);
    expect($attendance->presence_status)->toBe('A');
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
            ->where('roster.0.presence_status', 'P')
            ->where('roster.0.justification_status', null)
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
        'presence_status' => 'A', 'justification_status' => 'I', 'note' => 'Certificat médical reçu',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get("/timesheets/{$timesheet->id}")
        ->assertInertia(fn ($page) => $page
            ->where('roster.0.presence_status', 'A')
            ->where('roster.0.justification_status', 'I')
            ->where('roster.0.note', 'Certificat médical reçu')
        );
});

test('storing attendance creates one row per student and locks the timesheet', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student1 = enrollAttendanceStudent($section, $eleveRole, $school);
    $student2 = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    expect($timesheet->attendance_submitted_at)->toBeNull();

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student1->id, 'presence_status' => 'P', 'note' => null],
                ['section_user_id' => $student2->id, 'presence_status' => 'A', 'justification_status' => 'I', 'note' => 'Absent non justifié'],
            ],
        ])
        ->assertRedirect();

    expect(Attendance::count())->toBe(2);
    expect(Attendance::where('section_user_id', $student2->id)->first()->presence_status)->toBe('A');
    expect(Attendance::where('section_user_id', $student2->id)->first()->note)->toBe('Absent non justifié');
    expect($timesheet->fresh()->attendance_submitted_at)->not->toBeNull();
});

test('resubmitting attendance after it was already submitted is rejected and changes nothing', function () {
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
                ['section_user_id' => $student1->id, 'presence_status' => 'P', 'note' => null],
                ['section_user_id' => $student2->id, 'presence_status' => 'A', 'justification_status' => 'I', 'note' => 'Absent non justifié'],
            ],
        ])->assertRedirect();

    $submittedAt = $timesheet->fresh()->attendance_submitted_at;

    // Tentative de resoumission — rejetée, rien ne change.
    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student1->id, 'presence_status' => 'A', 'justification_status' => 'I', 'note' => 'Rentré chez lui malade'],
                ['section_user_id' => $student2->id, 'presence_status' => 'P', 'note' => null],
            ],
        ])->assertSessionHasErrors('attendances');

    expect(Attendance::where('section_user_id', $student1->id)->first()->presence_status)->toBe('P');
    expect(Attendance::where('section_user_id', $student2->id)->first()->presence_status)->toBe('A');
    expect($timesheet->fresh()->attendance_submitted_at->toDateTimeString())->toBe($submittedAt->toDateTimeString());
});

test('a retard is stored distinctly from an absence', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'presence_status' => 'R', 'justification_status' => 'J', 'note' => 'Retard de bus'],
            ],
        ])
        ->assertRedirect();

    $attendance = Attendance::where('section_user_id', $student->id)->first();
    expect($attendance->presence_status)->toBe('R');
    expect($attendance->justification_status)->toBe('J');
});

test('absent or retard without an explicit justification defaults to unjustified', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'presence_status' => 'A', 'note' => null],
            ],
        ])
        ->assertRedirect();

    expect(Attendance::where('section_user_id', $student->id)->first()->justification_status)->toBe('I');
});

test('present never carries a justification status even if the client sends one', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'presence_status' => 'P', 'justification_status' => 'J', 'note' => null],
            ],
        ])
        ->assertRedirect();

    expect(Attendance::where('section_user_id', $student->id)->first()->justification_status)->toBeNull();
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
                ['section_user_id' => $outsideStudent->id, 'presence_status' => 'A', 'justification_status' => 'I', 'note' => null],
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
                ['section_user_id' => $teacherSectionUser->id, 'presence_status' => 'A', 'justification_status' => 'I', 'note' => null],
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

test('created_by and updated_by are both set to the submitting user on first submission', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'presence_status' => 'P', 'note' => null],
            ],
        ]);

    $attendance = Attendance::where('section_user_id', $student->id)->first();
    expect($attendance->created_by)->toBe($powerUser->id);
    expect($attendance->updated_by)->toBe($powerUser->id);
});

test('storing attendance for a session that has not happened yet is rejected', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $futureDate = Carbon::tomorrow()->toDateString();
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr, $futureDate);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'presence_status' => 'P', 'note' => null],
            ],
        ])
        ->assertSessionHasErrors('attendances');

    expect(Attendance::count())->toBe(0);
});

test('migration backfills attendance_submitted_at for timesheets that already have attendance rows', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $otherSection = makeAttendanceSection($school, 'Classe B');
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));

    $timesheetWithAttendance = makeAttendanceSessionFor($school, $section, $teacherUsr);
    $student = enrollAttendanceStudent($section, $eleveRole, $school);
    Attendance::create([
        'timesheet_id' => $timesheetWithAttendance->id, 'section_user_id' => $student->id,
        'presence_status' => 'A', 'justification_status' => 'I', 'note' => 'Absence non justifiée',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $timesheetWithoutAttendance = makeAttendanceSessionFor($school, $otherSection, $teacherUsr);

    // Simule des données antérieures à cette migration : la colonne existe déjà
    // (la suite complète de migrations a tourné pour le setup de ce test), on
    // la supprime pour reproduire l'état pré-migration.
    Schema::table('timesheets', fn ($table) => $table->dropColumn('attendance_submitted_at'));

    (require database_path('migrations/2026_09_07_000004_add_attendance_submitted_at_to_timesheets_table.php'))->up();

    expect($timesheetWithAttendance->fresh()->attendance_submitted_at)->not->toBeNull();
    expect($timesheetWithoutAttendance->fresh()->attendance_submitted_at)->toBeNull();
});

test('storing attendance for a session happening today is accepted', function () {
    $school = makeAttendanceSchool();
    $section = makeAttendanceSection($school);
    $eleveRole = makeAttendanceRole('ELEVE', 'Élève');
    $teacherUsr = makeAttendanceUsr($school, makeAttendanceRole('PROF', 'Professeur'));
    $timesheet = makeAttendanceSessionFor($school, $section, $teacherUsr, Carbon::today()->toDateString());
    $student = enrollAttendanceStudent($section, $eleveRole, $school);

    $powerUser = makeAttendanceUsr($school, makeAttendanceRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$timesheet->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $student->id, 'presence_status' => 'P', 'note' => null],
            ],
        ])
        ->assertRedirect();

    expect(Attendance::count())->toBe(1);
});
