<?php

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\UserSchoolRole;

function makeScopingSchool(string $name = 'École A'): School
{
    return School::create([
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeScopingRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeScopingUsr(School $school, Role $role): UserSchoolRole
{
    $user = \App\Models\User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('a power user cannot view another schools classroom by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerRole = makeScopingRole('POWER', 'Power User');
    $powerUserA = makeScopingUsr($schoolA, $powerRole)->user;

    $classroomB = Classroom::create([
        'school_id' => $schoolB->id, 'name' => 'Salle B1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/classrooms/{$classroomB->id}")
        ->assertNotFound();
});

test('a power user can still view their own schools classroom', function () {
    $schoolA = makeScopingSchool('École A');
    $powerRole = makeScopingRole('POWER', 'Power User');
    $powerUserA = makeScopingUsr($schoolA, $powerRole)->user;

    $classroomA = Classroom::create([
        'school_id' => $schoolA->id, 'name' => 'Salle A1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/classrooms/{$classroomA->id}")
        ->assertOk();
});

test('a power user cannot view another schools course by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseB = Course::create([
        'school_id' => $schoolB->id, 'name' => 'Maths B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/courses/{$courseB->id}")
        ->assertNotFound();
});

test('a power user cannot view another schools section by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $sectionB = Section::create([
        'school_id' => $schoolB->id, 'name' => 'Classe B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/sections/{$sectionB->id}")
        ->assertNotFound();
});

test('a power user cannot view another schools lesson by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseB = Course::create([
        'school_id' => $schoolB->id, 'name' => 'Maths B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subjectB = Subject::create([
        'course_id' => $courseB->id, 'name' => 'Algèbre B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $lessonB = Lesson::create([
        'school_id' => $schoolB->id, 'subject_id' => $subjectB->id, 'name' => 'Leçon B1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/lessons/{$lessonB->id}")
        ->assertNotFound();
});

test('creating a resource persists its school_id instead of crashing', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $response = $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/resources', ['name' => 'Vidéoprojecteur']);

    $response->assertRedirect();
    $resource = Resource::where('name', 'Vidéoprojecteur')->firstOrFail();
    expect($resource->school_id)->toBe($schoolA->id);
});

test('a power user cannot view another schools resource by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $resourceB = Resource::create([
        'school_id' => $schoolB->id, 'name' => 'Imprimante B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/resources/{$resourceB->id}")
        ->assertNotFound();
});

/**
 * Construit une chaîne complète école → section → cours → section_course → schedule →
 * timesheet pour un professeur donné. Retourne toutes les entités créées.
 */
function makeScopingSessionFor(School $school, UserSchoolRole $teacherUsr): array
{
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe '.$school->name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths '.$school->name,
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
        'school_id' => $school->id, 'name' => 'Salle '.$school->name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre '.$school->name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-08-24', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return [
        'section' => $section, 'course' => $course, 'sectionCourse' => $sectionCourse,
        'schedule' => $schedule, 'timesheet' => $timesheet, 'subject' => $subject,
    ];
}

test('a power user cannot view another schools section-course by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionB = makeScopingSessionFor($schoolB, $teacherB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/section-courses/{$sessionB['sectionCourse']->id}")
        ->assertNotFound();
});

test('a power user cannot view another schools subject by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionB = makeScopingSessionFor($schoolB, $teacherB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/subjects/{$sessionB['subject']->id}")
        ->assertNotFound();
});

test('a power user cannot view another schools timesheet by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionB = makeScopingSessionFor($schoolB, $teacherB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/timesheets/{$sessionB['timesheet']->id}")
        ->assertNotFound();
});

test('a power user cannot view another schools schedule by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionB = makeScopingSessionFor($schoolB, $teacherB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/schedules/{$sessionB['schedule']->id}")
        ->assertNotFound();
});

test('a non-admin power user cannot assign roles via user-school-roles', function () {
    $school = makeScopingSchool('École A');
    $powerUser = makeScopingUsr($school, makeScopingRole('POWER', 'Power User'))->user;
    $targetUser = \App\Models\User::factory()->create();

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/user-school-roles', [
            'user_id' => $targetUser->id,
            'school_id' => $school->id,
            'role_id' => makeScopingRole('ADMIN', 'Administrateur')->id,
        ])
        ->assertForbidden();
});

test('an admin can still assign roles via user-school-roles', function () {
    $school = makeScopingSchool('École A');
    $adminRole = makeScopingRole('ADMIN', 'Administrateur');
    $admin = makeScopingUsr($school, $adminRole)->user;
    $targetUser = \App\Models\User::factory()->create();

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $school->id])
        ->post('/user-school-roles', [
            'user_id' => $targetUser->id,
            'school_id' => $school->id,
            'role_id' => makeScopingRole('PROF', 'Professeur')->id,
        ])
        ->assertRedirect();

    expect(\App\Models\UserSchoolRole::where('user_id', $targetUser->id)->where('school_id', $school->id)->exists())->toBeTrue();
});

test('a user cannot view another schools panel', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $userA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $this->actingAs($userA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/schools/{$schoolB->id}/panel")
        ->assertNotFound();
});

test('a multi-school user can view both of their schools panels', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolC = makeScopingSchool('École C');
    $role = makeScopingRole('POWER', 'Power User');
    $usrA = makeScopingUsr($schoolA, $role);
    $user = $usrA->user;
    UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $schoolC->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/schools/{$schoolA->id}/panel")
        ->assertOk();

    $this->actingAs($user)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/schools/{$schoolC->id}/panel")
        ->assertOk();
});

test('the edit timesheet schedule dropdown only lists the active schools schedules', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);
    makeScopingSessionFor($schoolB, $teacherB); // une autre école, ne doit pas apparaître

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/timesheets/{$sessionA['timesheet']->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->has('schedules', 1)
            ->where('schedules.0.id', $sessionA['schedule']->id)
        );
});

test('duplicating a weeks planning does not duplicate another schools timesheets', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    // Même semaine source (lundi 2026-08-24) pour les deux écoles
    $sessionA = makeScopingSessionFor($schoolA, $teacherA);
    makeScopingSessionFor($schoolB, $teacherB);

    $countBefore = \App\Models\Timesheet::count();

    $response = $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/planning/duplicate', [
            'source_week_start' => '2026-08-24',
            'year_end'          => '2026-08-31',
        ]);

    $response->assertRedirect();

    // Seul le timesheet de l'école A doit avoir été dupliqué (1 nouvelle semaine ajoutée)
    $countAfter = \App\Models\Timesheet::count();
    expect($countAfter - $countBefore)->toBe(1);

    $duplicated = \App\Models\Timesheet::where('id', '!=', $sessionA['timesheet']->id)
        ->latest('id')->first();
    expect($duplicated->user_school_role_id)->toBe($teacherA->id);
});

// ---- Write-side FK scoping (finding #1) ----

test('SubjectsController store rejects a course_id from another school', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseB = Course::create([
        'school_id' => $schoolB->id, 'name' => 'Maths B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/subjects', [
            'course_id' => $courseB->id,
            'name'      => 'Algèbre injectée',
        ])
        ->assertSessionHasErrors('course_id');
});

test('SubjectsController store accepts a legitimate same-school course_id', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseA = Course::create([
        'school_id' => $schoolA->id, 'name' => 'Maths A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/subjects', [
            'course_id' => $courseA->id,
            'name'      => 'Algèbre A',
        ])
        ->assertRedirect();

    expect(Subject::where('name', 'Algèbre A')->where('course_id', $courseA->id)->exists())->toBeTrue();
});

test('SchedulesController store rejects a section_course_id from another school', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionB = makeScopingSessionFor($schoolB, $teacherB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/schedules', [
            'section_course_id' => $sessionB['sectionCourse']->id,
            'name'              => 'Créneau injecté',
            'day_of_week'       => 2,
            'start_time'        => '08:00',
            'end_time'          => '09:00',
        ])
        ->assertSessionHasErrors('section_course_id');
});

test('SectionCoursesController store rejects a section_user_id from another school', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionB = makeScopingSessionFor($schoolB, $teacherB);
    $courseA = Course::create([
        'school_id' => $schoolA->id, 'name' => 'Maths A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // section_user_id vient de l'école B, course_id est légitime (école A)
    $sectionUserB = SectionUserSchoolRole::whereHas(
        'userschoolrole', fn ($q) => $q->where('school_id', $schoolB->id)
    )->firstOrFail();

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/section-courses', [
            'section_user_id'   => $sectionUserB->id,
            'course_id'         => $courseA->id,
            'name'              => 'Association injectée',
            'total_hours'       => 10,
            'hours_per_session' => 2,
        ])
        ->assertSessionHasErrors('section_user_id');
});

test('SectionCoursesController store rejects a course_id from another school', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);
    $courseB = Course::create([
        'school_id' => $schoolB->id, 'name' => 'Maths B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $sectionUserA = SectionUserSchoolRole::whereHas(
        'userschoolrole', fn ($q) => $q->where('school_id', $schoolA->id)
    )->firstOrFail();

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/section-courses', [
            'section_user_id'   => $sectionUserA->id,
            'course_id'         => $courseB->id,
            'name'              => 'Association injectée',
            'total_hours'       => 10,
            'hours_per_session' => 2,
        ])
        ->assertSessionHasErrors('course_id');
});

test('LessonsController store rejects a subject_id from another school', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionB = makeScopingSessionFor($schoolB, $teacherB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/lessons', [
            'subject_id' => $sessionB['subject']->id,
            'name'       => 'Leçon injectée',
        ])
        ->assertSessionHasErrors('subject_id');
});

test('TimesheetsController store rejects a subject_id from another school', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));
    $teacherB = makeScopingUsr($schoolB, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);
    $sessionB = makeScopingSessionFor($schoolB, $teacherB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/timesheets', [
            'user_school_role_id' => $teacherA->id,
            'schedule_id'         => $sessionA['schedule']->id,
            'subject_id'          => $sessionB['subject']->id,
            'classroom_id'        => Classroom::where('school_id', $schoolA->id)->firstOrFail()->id,
            'date'                => '2026-08-25',
            'hours_done'          => 2,
        ])
        ->assertSessionHasErrors('subject_id');
});

// ---- Soft-deleted intermediate parent must not 404 for same-school access (finding #2) ----

test('a subject is still reachable by its rightful owner after its course is soft-deleted', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);
    $sessionA['course']->delete();

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/subjects/{$sessionA['subject']->id}")
        ->assertOk();
});

test('a schedule is still reachable by its rightful owner after its course is soft-deleted', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);
    $sessionA['course']->delete();

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/schedules/{$sessionA['schedule']->id}")
        ->assertOk();
});

// ---- Positive controls: legitimate same-school access must still work ----
// `ScopesRouteBindingToActiveSchool` is applied per-model; a bug in one model's
// `applySchoolScope()` could 404 its own rightful owner without any of the
// "cannot view another school's X" tests above catching it. One positive test
// per scoped model closes that blind spot.

test('a power user can still view their own schools course', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseA = Course::create([
        'school_id' => $schoolA->id, 'name' => 'Maths A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/courses/{$courseA->id}")
        ->assertOk();
});

test('a power user can still view their own schools section', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $sectionA = Section::create([
        'school_id' => $schoolA->id, 'name' => 'Classe A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/sections/{$sectionA->id}")
        ->assertOk();
});

test('a power user can still view their own schools lesson', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseA = Course::create([
        'school_id' => $schoolA->id, 'name' => 'Maths A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subjectA = Subject::create([
        'course_id' => $courseA->id, 'name' => 'Algèbre A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $lessonA = Lesson::create([
        'school_id' => $schoolA->id, 'subject_id' => $subjectA->id, 'name' => 'Leçon A1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/lessons/{$lessonA->id}")
        ->assertOk();
});

test('a power user can still view their own schools resource', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $resourceA = Resource::create([
        'school_id' => $schoolA->id, 'name' => 'Imprimante A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/resources/{$resourceA->id}")
        ->assertOk();
});

test('a power user can still view their own schools section-course', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/section-courses/{$sessionA['sectionCourse']->id}")
        ->assertOk();
});

test('a power user can still view their own schools subject', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/subjects/{$sessionA['subject']->id}")
        ->assertOk();
});

test('a power user can still view their own schools timesheet', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/timesheets/{$sessionA['timesheet']->id}")
        ->assertOk();
});

test('a power user can still view their own schools schedule', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;
    $teacherA = makeScopingUsr($schoolA, makeScopingRole('PROF', 'Professeur'));

    $sessionA = makeScopingSessionFor($schoolA, $teacherA);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/schedules/{$sessionA['schedule']->id}")
        ->assertOk();
});
