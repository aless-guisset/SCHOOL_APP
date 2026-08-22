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
use App\Notifications\TimesheetAssignedNotification;
use App\Notifications\TimesheetCancelledNotification;
use Illuminate\Support\Facades\Notification;

function makeTsNotifSchool(): School
{
    return School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeTsNotifRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeTsNotifUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Section + cours + section_course + schedule + salle + matière, prêts pour un Timesheet. */
function makeTsNotifSession(School $school, UserSchoolRole $teacherUsr): array
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

    return compact('schedule', 'classroom', 'subject');
}

test('creating a timesheet notifies the assigned teacher', function () {
    Notification::fake();

    $school = makeTsNotifSchool();
    $powerUser = makeTsNotifUsr($school, makeTsNotifRole('POWER', 'Power User'))->user;
    $teacherUsr = makeTsNotifUsr($school, makeTsNotifRole('PROF', 'Professeur'));
    $session = makeTsNotifSession($school, $teacherUsr);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/timesheets', [
            'user_school_role_id' => $teacherUsr->id,
            'schedule_id'         => $session['schedule']->id,
            'subject_id'          => $session['subject']->id,
            'classroom_id'        => $session['classroom']->id,
            'date'                => '2026-09-07',
            'hours_done'          => 2,
        ])
        ->assertRedirect();

    Notification::assertSentTo($teacherUsr->user, TimesheetAssignedNotification::class);
    Notification::assertNotSentTo($powerUser, TimesheetAssignedNotification::class);
});

test('deleting a timesheet notifies the assigned teacher of the cancellation', function () {
    Notification::fake();

    $school = makeTsNotifSchool();
    $powerUser = makeTsNotifUsr($school, makeTsNotifRole('POWER', 'Power User'))->user;
    $teacherUsr = makeTsNotifUsr($school, makeTsNotifRole('PROF', 'Professeur'));
    $session = makeTsNotifSession($school, $teacherUsr);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacherUsr->id,
        'schedule_id' => $session['schedule']->id,
        'subject_id' => $session['subject']->id,
        'classroom_id' => $session['classroom']->id,
        'date' => '2026-09-07', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/timesheets/{$timesheet->id}")
        ->assertRedirect();

    Notification::assertSentTo($teacherUsr->user, TimesheetCancelledNotification::class);
});

test('a teacher who cancels their own timesheet does not notify themselves', function () {
    // Aujourd'hui aucune route n'autorise un Professeur à supprimer un timesheet
    // (can-manage requis), mais la garde "ne pas se notifier soi-même" est testée
    // directement au niveau du controller pour ne pas dépendre de ce détail de routage.
    Notification::fake();

    $school = makeTsNotifSchool();
    $powerUserUsr = makeTsNotifUsr($school, makeTsNotifRole('POWER', 'Power User'));
    $session = makeTsNotifSession($school, $powerUserUsr);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $powerUserUsr->id,
        'schedule_id' => $session['schedule']->id,
        'subject_id' => $session['subject']->id,
        'classroom_id' => $session['classroom']->id,
        'date' => '2026-09-07', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/timesheets/{$timesheet->id}")
        ->assertRedirect();

    Notification::assertNotSentTo($powerUserUsr->user, TimesheetCancelledNotification::class);
});
