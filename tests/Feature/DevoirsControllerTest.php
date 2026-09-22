<?php

use App\Models\Course;
use App\Models\Devoir;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionCourse;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeDevoirSchool(): School
{
    return School::create(['name' => 'École Devoirs', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeDevoirRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeDevoirUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeDevoirSectionCourse(School $school, UserSchoolRole $teacherUsr): SectionCourse
{
    $eleveRole = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $placeholderStudentUsr = makeDevoirUsr($school, $eleveRole);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe A', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionUser = SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $placeholderStudentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $course = Course::create(['school_id' => $school->id, 'name' => 'Maths', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $sectionCourse = SectionCourse::create(['section_user_id' => $sectionUser->id, 'course_id' => $course->id, 'total_hours' => 60, 'hours_per_session' => 2, 'name' => 'SC', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    Schedule::create(['section_course_id' => $sectionCourse->id, 'user_school_role_id' => $teacherUsr->id, 'name' => 'Lundi', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return $sectionCourse;
}

test('the teaching professor can create a devoir', function () {
    $school = makeDevoirSchool();
    $teacherUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $sc = makeDevoirSectionCourse($school, $teacherUsr);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/devoirs", [
            'title' => 'Exercices chapitre 3',
            'description' => 'Faire les exos 1 à 5',
            'due_date' => '2026-10-01',
        ])
        ->assertRedirect();

    $devoir = Devoir::where('section_course_id', $sc->id)->firstOrFail();
    expect($devoir->title)->toBe('Exercices chapitre 3');
    expect($devoir->created_by)->toBe($teacherUsr->user->id);
});

test('a colleague professor cannot create a devoir on another teachers course', function () {
    $school = makeDevoirSchool();
    $teacherUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $colleagueUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $sc = makeDevoirSectionCourse($school, $teacherUsr);

    $this->actingAs($colleagueUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/devoirs", [
            'title' => 'Intrusion', 'due_date' => '2026-10-01',
        ])
        ->assertForbidden();

    expect(Devoir::where('section_course_id', $sc->id)->count())->toBe(0);
});

test('Power User cannot create a devoir even though can-manage lets them through the gate', function () {
    $school = makeDevoirSchool();
    $teacherUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $sc = makeDevoirSectionCourse($school, $teacherUsr);
    $powerUser = makeDevoirUsr($school, makeDevoirRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/devoirs", [
            'title' => 'Intrusion', 'due_date' => '2026-10-01',
        ])
        ->assertForbidden();

    expect(Devoir::where('section_course_id', $sc->id)->count())->toBe(0);
});

test('the teaching professor can update their own devoir', function () {
    $school = makeDevoirSchool();
    $teacherUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $sc = makeDevoirSectionCourse($school, $teacherUsr);
    $devoir = Devoir::create(['section_course_id' => $sc->id, 'title' => 'Titre initial', 'due_date' => '2026-10-01', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->put("/devoirs/{$devoir->id}", ['title' => 'Titre modifié', 'due_date' => '2026-10-05'])
        ->assertRedirect();

    expect($devoir->fresh()->title)->toBe('Titre modifié');
});

test('a colleague professor cannot update or delete a devoir that is not theirs', function () {
    $school = makeDevoirSchool();
    $teacherUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $colleagueUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $sc = makeDevoirSectionCourse($school, $teacherUsr);
    $devoir = Devoir::create(['section_course_id' => $sc->id, 'title' => 'Titre', 'due_date' => '2026-10-01', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($colleagueUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->put("/devoirs/{$devoir->id}", ['title' => 'Piraté'])
        ->assertForbidden();

    $this->actingAs($colleagueUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/devoirs/{$devoir->id}")
        ->assertForbidden();

    expect($devoir->fresh()->title)->toBe('Titre');
    expect(Devoir::find($devoir->id))->not->toBeNull();
});

test('the teaching professor can delete their own devoir', function () {
    $school = makeDevoirSchool();
    $teacherUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $sc = makeDevoirSectionCourse($school, $teacherUsr);
    $devoir = Devoir::create(['section_course_id' => $sc->id, 'title' => 'À supprimer', 'due_date' => '2026-10-01', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/devoirs/{$devoir->id}")
        ->assertRedirect();

    expect(Devoir::find($devoir->id))->toBeNull();
});

test('store validates required title and due_date', function () {
    $school = makeDevoirSchool();
    $teacherUsr = makeDevoirUsr($school, makeDevoirRole('PROF', 'Professeur'));
    $sc = makeDevoirSectionCourse($school, $teacherUsr);

    $this->actingAs($teacherUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post("/section-courses/{$sc->id}/devoirs", [])
        ->assertSessionHasErrors(['title', 'due_date']);
});
