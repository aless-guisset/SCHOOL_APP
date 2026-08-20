<?php

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
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
