<?php

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
use Inertia\Testing\AssertableInertia as Assert;

function makeTimesheetSchool(): School
{
    return School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeTimesheetRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeTimesheetUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Crée section + section_user (prof) + section_course + schedule, retourne le Schedule. */
function makeTimesheetScheduleFor(School $school, UserSchoolRole $teacherUsr, string $sectionName = 'Classe A'): Schedule
{
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $section = Section::create([
        'school_id' => $school->id, 'name' => $sectionName,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $teacherUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionCourse = SectionCourse::create([
        'section_user_id' => $sectionUser->id, 'course_id' => $course->id,
        'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'Maths '.$sectionName,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return Schedule::create([
        'section_course_id' => $sectionCourse->id, 'name' => 'Lundi',
        'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('power user does not see another school schedules in the timesheets create payload', function () {
    $schoolA = makeTimesheetSchool();
    $schoolB = makeTimesheetSchool();

    $teacherA = makeTimesheetUsr($schoolA, makeTimesheetRole('PROF', 'Professeur'));
    $teacherB = makeTimesheetUsr($schoolB, makeTimesheetRole('PROF', 'Professeur'));

    $scheduleA = makeTimesheetScheduleFor($schoolA, $teacherA, 'Classe A');
    makeTimesheetScheduleFor($schoolB, $teacherB, 'Classe B');

    $powerUserA = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $powerUserA->id, 'school_id' => $schoolA->id,
        'role_id' => makeTimesheetRole('POWER', 'Power User')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->get('/timesheets/create')
        ->assertInertia(fn (Assert $page) => $page
            ->component('power-user/web/Timesheets/Create')
            ->has('schedules', 1)
            ->where('schedules.0.id', $scheduleA->id)
        );
});

test('checkConflict rejects a classroom and user_school_role belonging to another school', function () {
    $schoolA = makeTimesheetSchool();
    $schoolB = makeTimesheetSchool();

    $teacherA = makeTimesheetUsr($schoolA, makeTimesheetRole('PROF', 'Professeur'));
    $teacherB = makeTimesheetUsr($schoolB, makeTimesheetRole('PROF', 'Professeur'));

    $scheduleA = makeTimesheetScheduleFor($schoolA, $teacherA, 'Classe A');

    $classroomB = Classroom::create([
        'school_id' => $schoolB->id, 'name' => 'Salle B',
        'is_active' => true, 'created_by' => 1,
    ]);

    $powerUserA = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $powerUserA->id, 'school_id' => $schoolA->id,
        'role_id' => makeTimesheetRole('POWER', 'Power User')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->getJson('/timesheets/check-conflict?'.http_build_query([
            'schedule_id'         => $scheduleA->id,
            'date'                => '2026-09-07',
            'user_school_role_id' => $teacherB->id, // école B
            'classroom_id'        => $classroomB->id, // école B
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_school_role_id', 'classroom_id']);
});

test('checkConflict rejects a schedule belonging to another school', function () {
    $schoolA = makeTimesheetSchool();
    $schoolB = makeTimesheetSchool();

    $teacherA = makeTimesheetUsr($schoolA, makeTimesheetRole('PROF', 'Professeur'));
    $teacherB = makeTimesheetUsr($schoolB, makeTimesheetRole('PROF', 'Professeur'));

    $scheduleB = makeTimesheetScheduleFor($schoolB, $teacherB, 'Classe B');

    $classroomA = Classroom::create([
        'school_id' => $schoolA->id, 'name' => 'Salle A',
        'is_active' => true, 'created_by' => 1,
    ]);

    $powerUserA = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $powerUserA->id, 'school_id' => $schoolA->id,
        'role_id' => makeTimesheetRole('POWER', 'Power User')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->getJson('/timesheets/check-conflict?'.http_build_query([
            'schedule_id'         => $scheduleB->id, // école B
            'date'                => '2026-09-07',
            'user_school_role_id' => $teacherA->id,
            'classroom_id'        => $classroomA->id,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['schedule_id']);
});

test('store rejects a schedule, user_school_role, or classroom belonging to another school', function () {
    $schoolA = makeTimesheetSchool();
    $schoolB = makeTimesheetSchool();

    $teacherA = makeTimesheetUsr($schoolA, makeTimesheetRole('PROF', 'Professeur'));
    $teacherB = makeTimesheetUsr($schoolB, makeTimesheetRole('PROF', 'Professeur'));

    $scheduleA = makeTimesheetScheduleFor($schoolA, $teacherA, 'Classe A');
    $scheduleB = makeTimesheetScheduleFor($schoolB, $teacherB, 'Classe B');

    $classroomA = Classroom::create([
        'school_id' => $schoolA->id, 'name' => 'Salle A',
        'is_active' => true, 'created_by' => 1,
    ]);
    $classroomB = Classroom::create([
        'school_id' => $schoolB->id, 'name' => 'Salle B',
        'is_active' => true, 'created_by' => 1,
    ]);

    $courseA = Course::create([
        'school_id' => $schoolA->id, 'name' => 'Physique',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subjectA = Subject::create([
        'course_id' => $courseA->id, 'name' => 'Physique',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUserA = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $powerUserA->id, 'school_id' => $schoolA->id,
        'role_id' => makeTimesheetRole('POWER', 'Power User')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $basePayload = [
        'user_school_role_id' => $teacherA->id,
        'schedule_id'         => $scheduleA->id,
        'subject_id'          => $subjectA->id,
        'classroom_id'        => $classroomA->id,
        'date'                => '2026-09-07',
        'hours_done'          => 2,
    ];

    $this->actingAs($powerUserA)
        ->postJson('/timesheets', [...$basePayload, 'schedule_id' => $scheduleB->id]) // école B
        ->assertStatus(422)
        ->assertJsonValidationErrors(['schedule_id']);

    $this->actingAs($powerUserA)
        ->postJson('/timesheets', [...$basePayload, 'user_school_role_id' => $teacherB->id]) // école B
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_school_role_id']);

    $this->actingAs($powerUserA)
        ->postJson('/timesheets', [...$basePayload, 'classroom_id' => $classroomB->id]) // école B
        ->assertStatus(422)
        ->assertJsonValidationErrors(['classroom_id']);
});

test('index defaults to the current week when no period/date is given', function () {
    $school = makeTimesheetSchool();
    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;

    $response = $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/timesheets');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('power-user/web/Timesheets/Index')
        ->where('period', 'week')
        ->where('range_start', now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString())
        ->where('range_end', now()->startOfWeek(\Carbon\Carbon::MONDAY)->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString())
    );
});

test('index with period=month returns the full calendar month and only its timesheets', function () {
    $school = makeTimesheetSchool();
    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    $makeTs = fn (string $date) => Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => $date, 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $inAugust = $makeTs('2026-08-17');
    $alsoInAugust = $makeTs('2026-08-31');
    $inSeptember = $makeTs('2026-09-07');

    $response = $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/timesheets?period=month&date=2026-08-15');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', 'month')
        ->where('range_start', '2026-08-01')
        ->where('range_end', '2026-08-31')
        ->has('timesheets', 2)
    );
});

test('index with period=trimester spans a 3-month rolling window from the anchor month', function () {
    $school = makeTimesheetSchool();
    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    $makeTs = fn (string $date) => Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => $date, 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $makeTs('2026-08-17'); // dans le trimestre (mois de l'ancre)
    $makeTs('2026-10-15'); // dans le trimestre (3e mois : août+2)
    $makeTs('2026-11-01'); // hors trimestre (4e mois)

    $response = $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/timesheets?period=trimester&date=2026-08-15');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', 'trimester')
        ->where('range_start', '2026-08-01')
        ->where('range_end', '2026-10-31')
        ->has('timesheets', 2)
    );
});

test('index falls back to week for an invalid period value', function () {
    $school = makeTimesheetSchool();
    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/timesheets?period=decade')
        ->assertInertia(fn (Assert $page) => $page->where('period', 'week'));
});

test('update leaves is_customized false when only hours_done changes, or a tracked field is resubmitted unchanged', function () {
    $school = makeTimesheetSchool();
    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-09-07', 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($timesheet->fresh()->is_customized)->toBeFalse();

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->patch("/timesheets/{$timesheet->id}", ['hours_done' => 3]);

    expect($timesheet->fresh()->is_customized)->toBeFalse();

    // Même valeur soumise que l'existante : pas un vrai changement, pas de marquage.
    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->patch("/timesheets/{$timesheet->id}", ['classroom_id' => $classroom->id]);

    expect($timesheet->fresh()->is_customized)->toBeFalse();
});

test('update marks is_customized true when classroom_id genuinely changes', function () {
    $school = makeTimesheetSchool();
    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroomA = Classroom::create(['school_id' => $school->id, 'name' => 'Salle A', 'is_active' => true, 'created_by' => 1]);
    $classroomB = Classroom::create(['school_id' => $school->id, 'name' => 'Salle B', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroomA->id,
        'date' => '2026-09-07', 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($timesheet->fresh()->is_customized)->toBeFalse();

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->patch("/timesheets/{$timesheet->id}", ['classroom_id' => $classroomB->id]);

    expect($timesheet->fresh()->is_customized)->toBeTrue();
});

test('checkConflict returns the conflicting timesheet id and type, not just a message', function () {
    $school = makeTimesheetSchool();
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    $existing = Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-09-07', 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;

    $response = $this->actingAs($powerUser)
        ->getJson('/timesheets/check-conflict?'.http_build_query([
            'schedule_id' => $schedule->id, 'date' => '2026-09-07',
            'user_school_role_id' => $teacher->id, 'classroom_id' => $classroom->id,
        ]));

    $response->assertOk();
    $conflicts = $response->json('conflicts');
    // Même prof/salle/section réutilisés que l'existant ici : les 3 types de
    // conflit se déclenchent simultanément — checkConflict() les reporte
    // tous (contrairement à validate(), qui s'arrête au premier).
    expect($conflicts)->toHaveCount(3);
    $teacherConflict = collect($conflicts)->firstWhere('type', 'teacher');
    expect($teacherConflict['id'])->toBe($existing->id);
    expect($teacherConflict['message'])->toContain('professeur');
});

test('store with replace_conflict_ids deletes the genuinely conflicting timesheet and creates the new one', function () {
    $school = makeTimesheetSchool();
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    $existing = Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-09-07', 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->post('/timesheets', [
            'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
            'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
            'date' => '2026-09-07', 'hours_done' => 3,
            'replace_conflict_ids' => [$existing->id],
        ])
        ->assertRedirect();

    expect(Timesheet::find($existing->id))->toBeNull(); // soft-deleted
    expect(Timesheet::where('schedule_id', $schedule->id)->where('date', '2026-09-07')->count())->toBe(1);
    expect(Timesheet::where('schedule_id', $schedule->id)->where('date', '2026-09-07')->first()->hours_done)->toBe(3);
});

test('store ignores a replace_conflict_ids entry that is not actually conflicting', function () {
    $school = makeTimesheetSchool();
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    // Un timesheet totalement indépendant (autre créneau, autre date) — ne
    // doit jamais pouvoir être supprimé via replace_conflict_ids, même si
    // son id est envoyé, puisqu'il n'est en conflit avec rien ici.
    $unrelatedSchedule = makeTimesheetScheduleFor($school, $teacher, 'Classe indépendante');
    $unrelated = Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $unrelatedSchedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-09-14', 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->post('/timesheets', [
            'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
            'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
            'date' => '2026-09-07', 'hours_done' => 3,
            'replace_conflict_ids' => [$unrelated->id],
        ])
        ->assertRedirect();

    expect(Timesheet::find($unrelated->id))->not->toBeNull(); // jamais touché
});

test('store still rejects a real conflict when replace_conflict_ids is not provided', function () {
    $school = makeTimesheetSchool();
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));
    $schedule = makeTimesheetScheduleFor($school, $teacher);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $schedule->sectionCourse->course_id, 'name' => 'Algèbre', 'is_active' => true, 'created_by' => 1]);

    Timesheet::create([
        'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-09-07', 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->post('/timesheets', [
            'user_school_role_id' => $teacher->id, 'schedule_id' => $schedule->id,
            'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
            'date' => '2026-09-07', 'hours_done' => 3,
        ])
        ->assertSessionHasErrors('date');
});

test('index exposes the schools sections and filters timesheets by section_id', function () {
    $school = makeTimesheetSchool();
    $powerUser = makeTimesheetUsr($school, makeTimesheetRole('POWER', 'Power User'))->user;
    $teacher = makeTimesheetUsr($school, makeTimesheetRole('PROF', 'Professeur'));

    $scheduleA = makeTimesheetScheduleFor($school, $teacher, 'Classe A');
    $scheduleB = makeTimesheetScheduleFor($school, $teacher, 'Classe B');

    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'is_active' => true, 'created_by' => 1]);
    $subjectA = Subject::create(['course_id' => $scheduleA->sectionCourse->course_id, 'name' => 'Algèbre A', 'is_active' => true, 'created_by' => 1]);
    $subjectB = Subject::create(['course_id' => $scheduleB->sectionCourse->course_id, 'name' => 'Algèbre B', 'is_active' => true, 'created_by' => 1]);

    // Même date pour les deux, pour vérifier que seule la classe filtrée apparaît.
    $date = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();

    Timesheet::create(['user_school_role_id' => $teacher->id, 'schedule_id' => $scheduleA->id, 'subject_id' => $subjectA->id, 'classroom_id' => $classroom->id, 'date' => $date, 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    Timesheet::create(['user_school_role_id' => $teacher->id, 'schedule_id' => $scheduleB->id, 'subject_id' => $subjectB->id, 'classroom_id' => $classroom->id, 'date' => $date, 'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $sectionAId = $scheduleA->sectionCourse->sectionUser->section_id;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/timesheets')
        ->assertInertia(fn (Assert $page) => $page->has('sections', 2)->has('timesheets', 2));

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/timesheets?section_id='.$sectionAId)
        ->assertInertia(fn (Assert $page) => $page
            ->has('timesheets', 1)
            ->where('section_id', $sectionAId)
        );
});
