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
use App\Models\UserSchoolRole;

function makeDupSchool(): School
{
    return School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeDupRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeDupUsr(School $school, Role $role): UserSchoolRole
{
    $user = \App\Models\User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Section + cours + section_course + schedule + salle + matière + timesheet un lundi donné. */
function makeDupSession(School $school, UserSchoolRole $teacherUsr, string $mondayDate): array
{
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $teacherUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionCourse = SectionCourse::create([
        'section_user_id' => $sectionUser->id, 'course_id' => $course->id,
        'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $schedule = Schedule::create([
        'section_course_id' => $sectionCourse->id, 'name' => 'Lundi',
        'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => $mondayDate, 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return compact('schedule', 'timesheet');
}

test('duplicating a weeks planning returns an Inertia redirect with a success flash, not raw json', function () {
    // Régression : le controller renvoyait response()->json(...) alors que le
    // frontend appelle l'endpoint via router.post() d'Inertia, qui attend une
    // réponse Inertia (redirect + flash) — un JSON brut sans en-tête
    // X-Inertia déclenche une navigation plein écran vers le JSON au lieu
    // d'afficher le message inline dans la boîte de dialogue.
    $school = makeDupSchool();
    $powerUser = makeDupUsr($school, makeDupRole('POWER', 'Power User'))->user;
    $teacher = makeDupUsr($school, makeDupRole('PROF', 'Professeur'));

    makeDupSession($school, $teacher, '2026-08-24'); // un lundi

    $response = $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/planning/duplicate', [
            'source_week_start' => '2026-08-24',
            'year_end' => '2026-09-07',
        ]);

    // 2026-08-24 (source, exclue), 2026-08-31 et 2026-09-07 sont tous deux
    // ≤ year_end (09-07 inclus, car year_end est comparé en fin de journée)
    // → 2 semaines dupliquées.
    $response->assertRedirect();
    $response->assertSessionHas('flash.type', 'success');
    $response->assertSessionHas('flash.message', '2 créneau(x) générés.');
});

test('duplicating with no timesheets in the source week returns a validation error, not a raw json 422', function () {
    $school = makeDupSchool();
    $powerUser = makeDupUsr($school, makeDupRole('POWER', 'Power User'))->user;

    $response = $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/planning/duplicate', [
            'source_week_start' => '2026-08-24',
            'year_end' => '2026-09-07',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('source_week_start');
});

test('duplicating for the whole school year generates one timesheet per remaining week', function () {
    $school = makeDupSchool();
    $powerUser = makeDupUsr($school, makeDupRole('POWER', 'Power User'))->user;
    $teacher = makeDupUsr($school, makeDupRole('PROF', 'Professeur'));

    // Lundi 2026-08-24 → fin d'année 2026-09-14 (2026-08-24, 08-31, 09-07,
    // 09-14 sont tous des lundis) : la source + 3 semaines dupliquées.
    makeDupSession($school, $teacher, '2026-08-24');

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/planning/duplicate', [
            'source_week_start' => '2026-08-24',
            'year_end' => '2026-09-14',
        ])
        ->assertSessionHas('flash.message', '3 créneau(x) générés.');

    expect(Timesheet::whereHas('userSchoolRole', fn ($q) => $q->where('school_id', $school->id))->count())->toBe(4);
    expect(Timesheet::where('date', '2026-08-31')->exists())->toBeTrue();
    expect(Timesheet::where('date', '2026-09-07')->exists())->toBeTrue();
    expect(Timesheet::where('date', '2026-09-14')->exists())->toBeTrue();
});

test('a student cannot trigger a planning duplication', function () {
    $school = makeDupSchool();
    $teacherUsr = makeDupUsr($school, makeDupRole('PROF', 'Professeur'));
    makeDupSession($school, $teacherUsr, '2026-08-24');

    $eleve = makeDupUsr($school, makeDupRole('ELEVE', 'Élève'))->user;

    $this->actingAs($eleve)
        ->withSession(['active_school_id' => $school->id])
        ->post('/planning/duplicate', [
            'source_week_start' => '2026-08-24',
            'year_end' => '2026-09-07',
        ])
        ->assertForbidden();
});

test('a teacher can trigger a planning duplication', function () {
    // Power User/Secrétariat/Professeur gèrent le contenu académique
    // (EnsureCanManage) — le professeur a bien accès à cette action.
    $school = makeDupSchool();
    $teacherUsr = makeDupUsr($school, makeDupRole('PROF', 'Professeur'));
    makeDupSession($school, $teacherUsr, '2026-08-24');

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post('/planning/duplicate', [
            'source_week_start' => '2026-08-24',
            'year_end' => '2026-09-07',
        ])
        ->assertSessionHas('flash.type', 'success');
});

test('duplication skips a target date where the teacher already has a conflicting timesheet', function () {
    // Régression : la duplication créait des Timesheet sans jamais passer
    // par NoTimesheetConflict, contrairement à une création manuelle —
    // générer un an de planning pouvait donc double-booker silencieusement
    // un professeur déjà engagé ailleurs à cette date/heure.
    $school = makeDupSchool();
    $powerUser = makeDupUsr($school, makeDupRole('POWER', 'Power User'))->user;
    $teacherUsr = makeDupUsr($school, makeDupRole('PROF', 'Professeur'));

    makeDupSession($school, $teacherUsr, '2026-08-24'); // source : lundi 10h-12h

    // Le même prof est déjà engagé ailleurs le lundi suivant (2026-08-31),
    // sur un tout autre schedule/section, au même créneau horaire.
    $conflictingSession = makeDupSession($school, $teacherUsr, '2026-08-31');
    expect($conflictingSession['schedule']->start_time)->toBe('10:00:00');

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/planning/duplicate', [
            'source_week_start' => '2026-08-24',
            'year_end' => '2026-08-31',
        ])
        ->assertSessionHas('flash.message', '0 créneau(x) générés. 1 ignoré(s) pour cause de conflit (professeur/salle/section déjà occupé).');

    // Le timesheet du 2026-08-31 pré-existant (celui du conflit) reste seul :
    // aucun second timesheet n'a été créé pour le prof ce jour-là.
    expect(Timesheet::where('date', '2026-08-31')->count())->toBe(1);
});
