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

    return compact('school', 'powerUser', 'usr', 'sectionCourse', 'classroom', 'subject');
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
