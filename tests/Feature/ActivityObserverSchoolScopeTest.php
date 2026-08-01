<?php

use App\Models\ActivityLog;
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

test('activity log stores school_id for a model with a direct school_id column', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect(ActivityLog::forModel($course)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log resolves school_id through course for a Subject (no direct school_id column)', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect(ActivityLog::forModel($subject)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log resolves school_id through a soft-deleted course for a Subject', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $course->delete();

    $subject->update(['name' => 'Algèbre avancée']);

    expect(ActivityLog::forModel($subject)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log resolves school_id through a soft-deleted user_school_role for a Timesheet', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $role = Role::firstOrCreate(['reference' => 'PROF'], [
        'name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $user = User::factory()->create();
    $usr = UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $usr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionCourse = SectionCourse::create([
        'section_user_id' => $sectionUser->id, 'course_id' => $course->id,
        'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'Maths Classe A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $schedule = Schedule::create([
        'section_course_id' => $sectionCourse->id, 'name' => 'Lundi',
        'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // Le user_school_role (professeur) quitte l'école : soft-delete.
    $usr->delete();

    $timesheet = Timesheet::create([
        'user_school_role_id' => $usr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => now()->toDateString(), 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect(ActivityLog::forModel($timesheet)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log resolves school_id through a soft-deleted course for a Schedule (two-hop via SectionCourse)', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $role = Role::firstOrCreate(['reference' => 'PROF'], [
        'name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $user = User::factory()->create();
    $usr = UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $usr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionCourse = SectionCourse::create([
        'section_user_id' => $sectionUser->id, 'course_id' => $course->id,
        'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'Maths Classe A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $schedule = Schedule::create([
        'section_course_id' => $sectionCourse->id, 'name' => 'Lundi',
        'day_of_week' => 1, 'start_time' => '10:00:00', 'end_time' => '12:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // Le Course est soft-delete alors que le SectionCourse intermédiaire reste intact :
    // ceci isole le second maillon de la chaîne Schedule -> SectionCourse -> Course,
    // qui ne serait pas exercé par un simple soft-delete du SectionCourse lui-même.
    $course->delete();

    $schedule->update(['name' => 'Lundi (modifié)']);

    expect(ActivityLog::forModel($schedule)->latest()->first()->school_id)->toBe($school->id);
});

test('activity log leaves school_id null for User and Role', function () {
    $user = User::factory()->create();
    $role = Role::create([
        'name' => 'Test Role', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect(ActivityLog::forModel($user)->latest()->first()->school_id)->toBeNull();
    expect(ActivityLog::forModel($role)->latest()->first()->school_id)->toBeNull();
});
