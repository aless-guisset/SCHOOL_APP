<?php

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeSchedulesTestFixture(): array
{
    $school = School::create(['name' => 'École Schedules', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $powerRole = Role::firstOrCreate(['reference' => 'POWER'], ['name' => 'Power User', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherUser = User::factory()->create();
    $usr = UserSchoolRole::create(['user_id' => $teacherUser->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $powerUser = User::factory()->create();
    UserSchoolRole::create(['user_id' => $powerUser->id, 'school_id' => $school->id, 'role_id' => $powerRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return compact('school', 'powerUser', 'usr', 'sectionCourse', 'classroom', 'subject', 'section');
}

test('store persists the default teacher, subject and classroom on a schedule', function () {
    $f = makeSchedulesTestFixture();

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->post('/schedules', [
            'section_course_id' => $f['sectionCourse']->id,
            'name' => 'Lundi',
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'user_school_role_id' => $f['usr']->id,
            'subject_id' => $f['subject']->id,
            'classroom_id' => $f['classroom']->id,
        ])
        ->assertRedirect();

    $schedule = \App\Models\Schedule::where('section_course_id', $f['sectionCourse']->id)->firstOrFail();
    expect($schedule->user_school_role_id)->toBe($f['usr']->id);
    expect($schedule->subject_id)->toBe($f['subject']->id);
    expect($schedule->classroom_id)->toBe($f['classroom']->id);
});

test('store can omit the default teacher, subject and classroom', function () {
    $f = makeSchedulesTestFixture();

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->post('/schedules', [
            'section_course_id' => $f['sectionCourse']->id,
            'name' => 'Mardi',
            'day_of_week' => 2,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

test('store rejects a user_school_role_id from another school', function () {
    $f = makeSchedulesTestFixture();
    $otherSchool = School::create(['name' => 'Autre école', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherRole = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherTeacherUser = User::factory()->create();
    $otherUsr = UserSchoolRole::create(['user_id' => $otherTeacherUser->id, 'school_id' => $otherSchool->id, 'role_id' => $otherRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->post('/schedules', [
            'section_course_id' => $f['sectionCourse']->id,
            'name' => 'Lundi',
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'user_school_role_id' => $otherUsr->id,
            'subject_id' => $f['subject']->id,
            'classroom_id' => $f['classroom']->id,
        ])
        ->assertSessionHasErrors('user_school_role_id');
});

test('create provides teacher, subject and classroom options scoped to the active school', function () {
    $f = makeSchedulesTestFixture();

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules/create')
        ->assertInertia(fn ($page) => $page
            ->has('userSchoolRoles', 1)
            ->has('subjects', 1)
            ->has('classrooms', 1)
        );
});

test('index exposes the schools year_end_date', function () {
    $f = makeSchedulesTestFixture();
    $f['school']->update(['year_end_date' => '2027-06-30']);

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules')
        ->assertInertia(fn ($page) => $page
            ->where('school.year_end_date', '2027-06-30')
        );
});

test('index exposes the schools sections and filters schedules by section_id', function () {
    $f = makeSchedulesTestFixture();

    // Une deuxième section/cours/schedule dans la même école, pour vérifier
    // que le filtre isole bien une classe des autres.
    $section2 = Section::create(['school_id' => $f['school']->id, 'name' => 'Classe 2', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser2 = SectionUserSchoolRole::create(['section_id' => $section2->id, 'user_school_role_id' => $f['usr']->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse2 = SectionCourse::create(['section_user_id' => $sectionUser2->id, 'course_id' => Course::create(['school_id' => $f['school']->id, 'name' => 'Cours 2', 'status' => 'A', 'is_active' => true, 'created_by' => 1])->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC2', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    \App\Models\Schedule::create(['section_course_id' => $f['sectionCourse']->id, 'name' => 'Lundi', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\Schedule::create(['section_course_id' => $sectionCourse2->id, 'name' => 'Mardi', 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules')
        ->assertInertia(fn ($page) => $page->has('sections', 2));

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules?section_id='.$section2->id)
        ->assertInertia(fn ($page) => $page
            ->has('schedules', 1)
            ->where('section_id', $section2->id)
        );
});

test('index scopes schedules to the professors own sections, never a colleagues', function () {
    $f = makeSchedulesTestFixture();

    $otherTeacherUser = User::factory()->create();
    $profRole = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherUsr = UserSchoolRole::create(['user_id' => $otherTeacherUser->id, 'school_id' => $f['school']->id, 'role_id' => $profRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSection = Section::create(['school_id' => $f['school']->id, 'name' => 'Classe B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherCourse = Course::create(['school_id' => $f['school']->id, 'name' => 'Cours B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionUser = SectionUserSchoolRole::create(['section_id' => $otherSection->id, 'user_school_role_id' => $otherUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionCourse = SectionCourse::create(['section_user_id' => $otherSectionUser->id, 'course_id' => $otherCourse->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SCB', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    \App\Models\Schedule::create(['section_course_id' => $f['sectionCourse']->id, 'name' => 'Lundi A', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\Schedule::create(['section_course_id' => $otherSectionCourse->id, 'name' => 'Mardi B', 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($f['usr']->user)
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules')
        ->assertInertia(fn ($page) => $page
            ->has('schedules', 1)
            ->where('schedules.0.name', 'Lundi A')
            ->has('sections', 1)
        );
});

test('index scopes schedules to the students own section, including other teachers in that section', function () {
    $f = makeSchedulesTestFixture();
    $eleveRole = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $eleveUser = User::factory()->create();
    $eleveUsr = UserSchoolRole::create(['user_id' => $eleveUser->id, 'school_id' => $f['school']->id, 'role_id' => $eleveRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    SectionUserSchoolRole::create(['section_id' => $f['section']->id, 'user_school_role_id' => $eleveUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $otherSection = Section::create(['school_id' => $f['school']->id, 'name' => 'Classe B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherCourse = Course::create(['school_id' => $f['school']->id, 'name' => 'Cours B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionUser = SectionUserSchoolRole::create(['section_id' => $otherSection->id, 'user_school_role_id' => $f['usr']->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionCourse = SectionCourse::create(['section_user_id' => $otherSectionUser->id, 'course_id' => $otherCourse->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SCB', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    \App\Models\Schedule::create(['section_course_id' => $f['sectionCourse']->id, 'name' => 'Lundi A', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\Schedule::create(['section_course_id' => $otherSectionCourse->id, 'name' => 'Mardi B', 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($eleveUser)
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules')
        ->assertInertia(fn ($page) => $page
            ->has('schedules', 1)
            ->where('schedules.0.name', 'Lundi A')
        );
});

test('index as_parent=1 scopes to the active childs section and switches with the active child', function () {
    $f = makeSchedulesTestFixture();
    $eleveRole = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parentRole = Role::firstOrCreate(['reference' => 'PARENT'], ['name' => 'Parent', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $childAUser = User::factory()->create();
    $childAUsr = UserSchoolRole::create(['user_id' => $childAUser->id, 'school_id' => $f['school']->id, 'role_id' => $eleveRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    SectionUserSchoolRole::create(['section_id' => $f['section']->id, 'user_school_role_id' => $childAUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $sectionB = Section::create(['school_id' => $f['school']->id, 'name' => 'Classe B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $courseB = Course::create(['school_id' => $f['school']->id, 'name' => 'Cours B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherBInSectionB = SectionUserSchoolRole::create(['section_id' => $sectionB->id, 'user_school_role_id' => $f['usr']->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourseB = SectionCourse::create(['section_user_id' => $teacherBInSectionB->id, 'course_id' => $courseB->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SCB', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $childBUser = User::factory()->create();
    $childBUsr = UserSchoolRole::create(['user_id' => $childBUser->id, 'school_id' => $f['school']->id, 'role_id' => $eleveRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    SectionUserSchoolRole::create(['section_id' => $sectionB->id, 'user_school_role_id' => $childBUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    \App\Models\Schedule::create(['section_course_id' => $f['sectionCourse']->id, 'name' => 'Lundi A', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\Schedule::create(['section_course_id' => $sectionCourseB->id, 'name' => 'Mardi B', 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create(['user_id' => $parent->id, 'school_id' => $f['school']->id, 'role_id' => $parentRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $childAUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $linkB = \App\Models\ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $childBUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    // Enfant A actif par défaut (premier lien créé, aucune sélection en session).
    $this->actingAs($parent)
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules?as_parent=1')
        ->assertInertia(fn ($page) => $page
            ->has('schedules', 1)
            ->where('schedules.0.name', 'Lundi A')
            ->where('viewing_child', "{$childAUser->firstname} {$childAUser->lastname}")
        );

    // Bascule vers l'enfant B.
    $this->actingAs($parent)
        ->withSession(['active_school_id' => $f['school']->id, 'active_child_link_id' => $linkB->id])
        ->get('/schedules?as_parent=1')
        ->assertInertia(fn ($page) => $page
            ->has('schedules', 1)
            ->where('schedules.0.name', 'Mardi B')
        );
});

test('index ignores a section_id filter for a section the professor does not teach', function () {
    $f = makeSchedulesTestFixture();
    $otherSection = Section::create(['school_id' => $f['school']->id, 'name' => 'Classe B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherCourse = Course::create(['school_id' => $f['school']->id, 'name' => 'Cours B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherTeacherUser = User::factory()->create();
    $profRole = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherUsr = UserSchoolRole::create(['user_id' => $otherTeacherUser->id, 'school_id' => $f['school']->id, 'role_id' => $profRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionUser = SectionUserSchoolRole::create(['section_id' => $otherSection->id, 'user_school_role_id' => $otherUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionCourse = SectionCourse::create(['section_user_id' => $otherSectionUser->id, 'course_id' => $otherCourse->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SCB', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    \App\Models\Schedule::create(['section_course_id' => $otherSectionCourse->id, 'name' => 'Mardi B', 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($f['usr']->user)
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules?section_id='.$otherSection->id)
        ->assertInertia(fn ($page) => $page->has('schedules', 0));
});

test('show returns 404 for a professor viewing a schedule outside their own sections', function () {
    $f = makeSchedulesTestFixture();
    $otherSection = Section::create(['school_id' => $f['school']->id, 'name' => 'Classe B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherCourse = Course::create(['school_id' => $f['school']->id, 'name' => 'Cours B', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherTeacherUser = User::factory()->create();
    $profRole = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherUsr = UserSchoolRole::create(['user_id' => $otherTeacherUser->id, 'school_id' => $f['school']->id, 'role_id' => $profRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionUser = SectionUserSchoolRole::create(['section_id' => $otherSection->id, 'user_school_role_id' => $otherUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSectionCourse = SectionCourse::create(['section_user_id' => $otherSectionUser->id, 'course_id' => $otherCourse->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SCB', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherSchedule = \App\Models\Schedule::create(['section_course_id' => $otherSectionCourse->id, 'name' => 'Mardi B', 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($f['usr']->user)
        ->withSession(['active_school_id' => $f['school']->id])
        ->get("/schedules/{$otherSchedule->id}")
        ->assertNotFound();
});

test('show remains fully accessible to Power User regardless of section', function () {
    $f = makeSchedulesTestFixture();
    $schedule = \App\Models\Schedule::create(['section_course_id' => $f['sectionCourse']->id, 'name' => 'Lundi A', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($f['powerUser'])
        ->withSession(['active_school_id' => $f['school']->id])
        ->get("/schedules/{$schedule->id}")
        ->assertOk();
});

test('index as_parent=1 without an active Parent role returns an empty schedule list, not an error', function () {
    $f = makeSchedulesTestFixture();
    \App\Models\Schedule::create(['section_course_id' => $f['sectionCourse']->id, 'name' => 'Lundi A', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($f['usr']->user)
        ->withSession(['active_school_id' => $f['school']->id])
        ->get('/schedules?as_parent=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('schedules', 0));
});
