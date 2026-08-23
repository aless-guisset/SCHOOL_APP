<?php

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\School;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\UserSchoolRole;

test('school casts year_end_date as a date', function () {
    $school = School::create([
        'name' => 'École Test', 'status' => 'A', 'is_active' => true,
        'year_end_date' => '2027-06-30', 'created_by' => 1,
    ]);

    expect($school->fresh()->year_end_date)->toBeInstanceOf(\Carbon\CarbonInterface::class);
    expect($school->fresh()->year_end_date->toDateString())->toBe('2027-06-30');
});

test('schedule can carry a default teacher, subject and classroom, and resolves the relations', function () {
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherUser = User::factory()->create();
    $usr = UserSchoolRole::create(['user_id' => $teacherUser->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $schedule = Schedule::create([
        'section_course_id' => $sectionCourse->id,
        'user_school_role_id' => $usr->id,
        'subject_id' => $subject->id,
        'classroom_id' => $classroom->id,
        'name' => 'Lundi', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($schedule->fresh()->userSchoolRole->id)->toBe($usr->id);
    expect($schedule->fresh()->subject->id)->toBe($subject->id);
    expect($schedule->fresh()->classroom->id)->toBe($classroom->id);
});

test('timesheet casts is_customized as boolean and defaults to false', function () {
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'PROF2'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $teacherUser = User::factory()->create();
    $usr = UserSchoolRole::create(['user_id' => $teacherUser->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Salle', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $schedule = Schedule::create(['section_course_id' => $sectionCourse->id, 'name' => 'Lundi', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $timesheet = Timesheet::create([
        'user_school_role_id' => $usr->id, 'schedule_id' => $schedule->id, 'subject_id' => $subject->id,
        'classroom_id' => $classroom->id, 'date' => '2026-09-01', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($timesheet->fresh()->is_customized)->toBeFalse();

    $timesheet->update(['is_customized' => true]);
    expect($timesheet->fresh()->is_customized)->toBeTrue();
});
