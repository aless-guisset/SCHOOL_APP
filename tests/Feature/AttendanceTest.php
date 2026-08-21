<?php

use App\Models\Attendance;
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

function makeSchool(): School
{
    return School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeSection(School $school, string $name = 'Classe A'): Section
{
    return Section::create([
        'school_id' => $school->id, 'name' => $name,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function enrollStudent(Section $section, Role $eleveRole, School $school): SectionUserSchoolRole
{
    $studentUsr = makeUsr($school, $eleveRole);

    return SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

/** Crée course + section_user (prof) + section_course + schedule + timesheet. Retourne le Timesheet. */
function makeSessionFor(School $school, Section $section, UserSchoolRole $teacherUsr): Timesheet
{
    $course = Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
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
        'school_id' => $school->id, 'name' => 'Salle A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => '2026-08-24', 'hours_done' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('attendance belongs to a timesheet and a section user', function () {
    $school = makeSchool();
    $section = makeSection($school);
    $teacherUsr = makeUsr($school, makeRole('PROF', 'Professeur'));
    $timesheet = makeSessionFor($school, $section, $teacherUsr);
    $student = enrollStudent($section, makeRole('ELEVE', 'Élève'), $school);

    $attendance = Attendance::create([
        'timesheet_id' => $timesheet->id, 'section_user_id' => $student->id,
        'is_present' => false, 'note' => 'Certificat médical reçu',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($attendance->timesheet->id)->toBe($timesheet->id);
    expect($attendance->sectionUser->id)->toBe($student->id);
    expect($attendance->is_present)->toBeFalse();
});
