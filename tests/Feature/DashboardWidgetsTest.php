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
use Inertia\Testing\AssertableInertia as Assert;

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

/** Crée section + section_user (prof) + section_course + schedule, retourne le Schedule. */
function makeScheduleFor(School $school, UserSchoolRole $teacherUsr, string $sectionName = 'Classe A'): Schedule
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

test('user school role exposes its section_users rows via sectionUserRoles', function () {
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
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $usr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($usr->sectionUserRoles)->toHaveCount(1);
    expect($usr->sectionUserRoles->first()->id)->toBe($sectionUser->id);
});

test('power user sees every schedule of the active school in week_schedule', function () {
    $school = makeSchool();
    $teacherUsr = makeUsr($school, makeRole('PROF', 'Professeur'));
    makeScheduleFor($school, $teacherUsr);

    $powerUser = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $powerUser->id, 'school_id' => $school->id,
        'role_id' => makeRole('POWER', 'Power User')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('week_schedule.slots', 1)
            ->where('week_schedule.slots.0.course_label', 'Maths Classe A')
        );
});

test('professeur only sees their own schedules in week_schedule', function () {
    $school = makeSchool();
    $profRole = makeRole('PROF', 'Professeur');
    $teacherUsr = makeUsr($school, $profRole);
    $otherTeacherUsr = makeUsr($school, $profRole);

    makeScheduleFor($school, $teacherUsr, 'Classe A');
    makeScheduleFor($school, $otherTeacherUsr, 'Classe B');

    $this->actingAs($teacherUsr->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('week_schedule.slots', 1)
            ->where('week_schedule.slots.0.course_label', 'Maths Classe A')
        );
});

test('eleve only sees schedules of their own section in week_schedule', function () {
    $school = makeSchool();
    $profRole = makeRole('PROF', 'Professeur');
    $teacherUsr = makeUsr($school, $profRole);
    $otherTeacherUsr = makeUsr($school, $profRole);

    $scheduleA = makeScheduleFor($school, $teacherUsr, 'Classe A');
    makeScheduleFor($school, $otherTeacherUsr, 'Classe B');

    // L'élève rejoint la même section (Classe A) que le premier prof
    $eleveUsr = makeUsr($school, makeRole('ELEVE', 'Élève'));
    $sectionA = $scheduleA->sectionCourse->sectionUser->section;
    SectionUserSchoolRole::create([
        'section_id' => $sectionA->id, 'user_school_role_id' => $eleveUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($eleveUsr->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('week_schedule.slots', 1)
            ->where('week_schedule.slots.0.course_label', 'Maths Classe A')
        );
});

test('week_schedule overlays teacher, classroom and subject when a timesheet exists this week', function () {
    $school = makeSchool();
    $teacherUsr = makeUsr($school, makeRole('PROF', 'Professeur'));
    $schedule = makeScheduleFor($school, $teacherUsr);

    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $course = $schedule->sectionCourse->course;
    $subject = Subject::create([
        'course_id' => $course->id, 'name' => 'Algèbre',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    Timesheet::create([
        'user_school_role_id' => $teacherUsr->id, 'schedule_id' => $schedule->id,
        'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        'date' => now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString(),
        'hours_done' => 2, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $powerUser = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $powerUser->id, 'school_id' => $school->id,
        'role_id' => makeRole('POWER', 'Power User')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('week_schedule.slots.0.classroom', 'Salle A')
            ->where('week_schedule.slots.0.subject', 'Algèbre')
            ->where('week_schedule.slots.0.teacher', fn ($name) => str_contains($name, $teacherUsr->user->lastname))
        );
});

test('power user sees recent activity scoped to their school', function () {
    $school = makeSchool();
    $otherSchool = makeSchool();

    $powerUser = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $powerUser->id, 'school_id' => $school->id,
        'role_id' => makeRole('POWER', 'Power User')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // Génère une entrée ActivityLog pour l'école active (via l'observer sur Course)
    Course::create([
        'school_id' => $school->id, 'name' => 'Maths',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    // Et une pour une AUTRE école — ne doit pas apparaître
    Course::create([
        'school_id' => $otherSchool->id, 'name' => 'Physique',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // Note : la création de $school et du UserSchoolRole du power user déclenche elle aussi
    // l'observer (Task 3), scopée à $school->id — le widget doit donc voir 3 entrées
    // (School, UserSchoolRole, Course "Maths"), et surtout PAS l'entrée Course "Physique"
    // de $otherSchool.
    $this->actingAs($powerUser)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('recent_activity', 3)
            ->where('recent_activity', fn ($items) => collect($items)->pluck('model_label')->contains('Maths')
                && ! collect($items)->pluck('model_label')->contains('Physique'))
            ->where('recent_activity', fn ($items) => collect($items)->pluck('model_type')->contains('Course'))
        );
});

test('recent_activity is null for professeur and eleve', function () {
    $school = makeSchool();
    $teacherUsr = makeUsr($school, makeRole('PROF', 'Professeur'));

    $this->actingAs($teacherUsr->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recent_activity', null)
        );
});
