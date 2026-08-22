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

function makeTsUpdateSchool(string $name = 'École'): School
{
    return School::create(['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeTsUpdateRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeTsUpdateUsr(School $school, Role $role): UserSchoolRole
{
    $user = \App\Models\User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/**
 * Builds a full course/section/schedule/subject/classroom/timesheet fixture
 * for a given school + teacher, mirroring the shape TimesheetsController
 * expects on update.
 */
function makeTsUpdateSession(School $school, UserSchoolRole $teacherUsr): array
{
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe '.$school->name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Cours '.$school->name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $teacherUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionCourse = SectionCourse::create([
        'section_user_id' => $sectionUser->id, 'course_id' => $course->id,
        'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC '.$school->name,
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
        'course_id' => $course->id, 'name' => 'Matière '.$school->name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return compact('schedule', 'classroom', 'subject');
}

test('updating a timesheet persists a changed schedule, subject and classroom', function () {
    $school = makeTsUpdateSchool();
    $powerUser = makeTsUpdateUsr($school, makeTsUpdateRole('POWER', 'Power User'))->user;
    $teacher = makeTsUpdateUsr($school, makeTsUpdateRole('PROF', 'Professeur'));

    $original = makeTsUpdateSession($school, $teacher);
    $replacement = makeTsUpdateSession($school, $teacher);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacher->id,
        'schedule_id' => $original['schedule']->id,
        'subject_id' => $original['subject']->id,
        'classroom_id' => $original['classroom']->id,
        'date' => '2026-08-24', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->patch("/timesheets/{$timesheet->id}", [
            'schedule_id' => $replacement['schedule']->id,
            'subject_id' => $replacement['subject']->id,
            'classroom_id' => $replacement['classroom']->id,
            'hours_done' => 3,
        ])
        ->assertRedirect("/timesheets/{$timesheet->id}");

    $timesheet->refresh();

    expect($timesheet->schedule_id)->toBe($replacement['schedule']->id);
    expect($timesheet->subject_id)->toBe($replacement['subject']->id);
    expect($timesheet->classroom_id)->toBe($replacement['classroom']->id);
    expect((float) $timesheet->hours_done)->toBe(3.0);
});

test('updating a timesheet rejects a schedule_id from another school', function () {
    $schoolA = makeTsUpdateSchool('École A');
    $schoolB = makeTsUpdateSchool('École B');
    $powerUserA = makeTsUpdateUsr($schoolA, makeTsUpdateRole('POWER', 'Power User'))->user;
    $teacherA = makeTsUpdateUsr($schoolA, makeTsUpdateRole('PROF', 'Professeur'));
    $teacherB = makeTsUpdateUsr($schoolB, makeTsUpdateRole('PROF', 'Professeur'));

    $sessionA = makeTsUpdateSession($schoolA, $teacherA);
    $sessionB = makeTsUpdateSession($schoolB, $teacherB);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacherA->id,
        'schedule_id' => $sessionA['schedule']->id,
        'subject_id' => $sessionA['subject']->id,
        'classroom_id' => $sessionA['classroom']->id,
        'date' => '2026-08-24', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->patch("/timesheets/{$timesheet->id}", [
            'schedule_id' => $sessionB['schedule']->id,
        ])
        ->assertSessionHasErrors('schedule_id');

    expect($timesheet->refresh()->schedule_id)->toBe($sessionA['schedule']->id);
});
