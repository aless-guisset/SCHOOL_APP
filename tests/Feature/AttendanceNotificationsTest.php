<?php

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Notifications\AbsenceRecordedNotification;
use Illuminate\Support\Facades\Notification;

function makeAbsNotifSchool(): School
{
    return School::create(['name' => 'École AbsNotif '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeAbsNotifRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeAbsNotifUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Section + cours + section_course + schedule + timesheet + élève inscrit, prêts pour prendre les présences. */
function makeAbsNotifSession(School $school, UserSchoolRole $teacherUsr): array
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherSectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $teacherUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $teacherSectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $schedule = Schedule::create(['section_course_id' => $sectionCourse->id, 'name' => 'Lundi', 'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => now()->subDay()->toDateString(), 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $studentUsr = makeAbsNotifUsr($school, makeAbsNotifRole('ELEVE', 'Élève'));
    $studentSectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return compact('timesheet', 'studentUsr', 'studentSectionUser');
}

function linkAbsNotifParent(UserSchoolRole $student, User $parent, School $school): void
{
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id,
        'role_id' => makeAbsNotifRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('marking a student absent notifies the linked parent', function () {
    Notification::fake();

    $school = makeAbsNotifSchool();
    $teacherUsr = makeAbsNotifUsr($school, makeAbsNotifRole('PROF', 'Professeur'));
    $session = makeAbsNotifSession($school, $teacherUsr);
    $parent = User::factory()->create();
    linkAbsNotifParent($session['studentUsr'], $parent, $school);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$session['timesheet']->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => false, 'note' => null],
            ],
        ])
        ->assertRedirect();

    Notification::assertSentTo($parent, AbsenceRecordedNotification::class);
});

test('marking a student present does not notify the parent', function () {
    Notification::fake();

    $school = makeAbsNotifSchool();
    $teacherUsr = makeAbsNotifUsr($school, makeAbsNotifRole('PROF', 'Professeur'));
    $session = makeAbsNotifSession($school, $teacherUsr);
    $parent = User::factory()->create();
    linkAbsNotifParent($session['studentUsr'], $parent, $school);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$session['timesheet']->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => true, 'note' => null],
            ],
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('correcting a student from present to absent notifies the parent', function () {
    Notification::fake();

    $school = makeAbsNotifSchool();
    $teacherUsr = makeAbsNotifUsr($school, makeAbsNotifRole('PROF', 'Professeur'));
    $session = makeAbsNotifSession($school, $teacherUsr);
    $parent = User::factory()->create();
    linkAbsNotifParent($session['studentUsr'], $parent, $school);

    $jar = $this->actingAs($teacherUsr->user)->withSession(['active_school_id' => $school->id]);

    $jar->post("/timesheets/{$session['timesheet']->id}/attendance", [
        'attendances' => [
            ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => true, 'note' => null],
        ],
    ])->assertRedirect();

    Notification::assertNothingSent();

    $jar->post("/timesheets/{$session['timesheet']->id}/attendance", [
        'attendances' => [
            ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => false, 'note' => null],
        ],
    ])->assertRedirect();

    Notification::assertSentTo($parent, AbsenceRecordedNotification::class);
});

test('correcting a student from absent to present does not notify the parent', function () {
    Notification::fake();

    $school = makeAbsNotifSchool();
    $teacherUsr = makeAbsNotifUsr($school, makeAbsNotifRole('PROF', 'Professeur'));
    $session = makeAbsNotifSession($school, $teacherUsr);
    $parent = User::factory()->create();
    linkAbsNotifParent($session['studentUsr'], $parent, $school);

    $jar = $this->actingAs($teacherUsr->user)->withSession(['active_school_id' => $school->id]);

    $jar->post("/timesheets/{$session['timesheet']->id}/attendance", [
        'attendances' => [
            ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => false, 'note' => null],
        ],
    ])->assertRedirect();

    Notification::assertSentTo($parent, AbsenceRecordedNotification::class);
    Notification::fake();

    $jar->post("/timesheets/{$session['timesheet']->id}/attendance", [
        'attendances' => [
            ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => true, 'note' => null],
        ],
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('re-saving the same absence with only the note changed does not duplicate the notification', function () {
    Notification::fake();

    $school = makeAbsNotifSchool();
    $teacherUsr = makeAbsNotifUsr($school, makeAbsNotifRole('PROF', 'Professeur'));
    $session = makeAbsNotifSession($school, $teacherUsr);
    $parent = User::factory()->create();
    linkAbsNotifParent($session['studentUsr'], $parent, $school);

    $jar = $this->actingAs($teacherUsr->user)->withSession(['active_school_id' => $school->id]);

    $jar->post("/timesheets/{$session['timesheet']->id}/attendance", [
        'attendances' => [
            ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => false, 'note' => null],
        ],
    ])->assertRedirect();

    $jar->post("/timesheets/{$session['timesheet']->id}/attendance", [
        'attendances' => [
            ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => false, 'note' => 'Justifiée'],
        ],
    ])->assertRedirect();

    Notification::assertSentToTimes($parent, AbsenceRecordedNotification::class, 1);
});

test('marking a student with no linked parent absent does not error', function () {
    Notification::fake();

    $school = makeAbsNotifSchool();
    $teacherUsr = makeAbsNotifUsr($school, makeAbsNotifRole('PROF', 'Professeur'));
    $session = makeAbsNotifSession($school, $teacherUsr);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/timesheets/{$session['timesheet']->id}/attendance", [
            'attendances' => [
                ['section_user_id' => $session['studentSectionUser']->id, 'is_present' => false, 'note' => null],
            ],
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});
