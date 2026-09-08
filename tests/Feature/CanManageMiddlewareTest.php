<?php

use App\Models\Classroom;
use App\Models\Role;
use App\Models\School;
use App\Models\UserSchoolRole;

function makeCanManageSchool(): School
{
    return School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeCanManageRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeCanManageUsr(School $school, Role $role): UserSchoolRole
{
    $user = \App\Models\User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

// ── Lecture ouverte à tout rôle de l'école active ──────────────────────────

test('a student can read the classroom list and a single classroom', function () {
    $school = makeCanManageSchool();
    $student = makeCanManageUsr($school, makeCanManageRole('ELEVE', 'Élève'))->user;

    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle 1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->get('/classrooms')
        ->assertOk();

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->get("/classrooms/{$classroom->id}")
        ->assertOk();
});

// ── Écriture : Power User, Secrétariat, Professeur ─────────────────────────

test('a power user can create, edit and delete a classroom', function () {
    $school = makeCanManageSchool();
    $powerUser = makeCanManageUsr($school, makeCanManageRole('POWER', 'Power User'))->user;

    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle 1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle 2'])->assertRedirect();
    expect(Classroom::where('name', 'Salle 2')->exists())->toBeTrue();

    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->patch("/classrooms/{$classroom->id}", ['name' => 'Renommée'])->assertRedirect();
    expect($classroom->refresh()->name)->toBe('Renommée');

    $this->actingAs($powerUser)->withSession(['active_school_id' => $school->id])
        ->delete("/classrooms/{$classroom->id}")->assertRedirect();
    expect(Classroom::find($classroom->id))->toBeNull();
});

test('a secretariat member can create a classroom', function () {
    $school = makeCanManageSchool();
    $secretariat = makeCanManageUsr($school, makeCanManageRole('SEC', 'Secrétariat'))->user;

    $this->actingAs($secretariat)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle 3'])
        ->assertRedirect();

    expect(Classroom::where('name', 'Salle 3')->exists())->toBeTrue();
});

test('a teacher cannot create, edit or delete a classroom (moved to can-manage-structure)', function () {
    $school = makeCanManageSchool();
    $teacher = makeCanManageUsr($school, makeCanManageRole('PROF', 'Professeur'))->user;

    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle 1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($teacher)->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle 4'])->assertForbidden();

    $this->actingAs($teacher)->withSession(['active_school_id' => $school->id])
        ->patch("/classrooms/{$classroom->id}", ['name' => 'Renommée par prof'])->assertForbidden();

    $this->actingAs($teacher)->withSession(['active_school_id' => $school->id])
        ->delete("/classrooms/{$classroom->id}")->assertForbidden();

    expect($classroom->refresh()->name)->toBe('Salle 1');
});

// ── Pas d'écriture : Élève, Administrateur, Directeur ──────────────────────

test('a student cannot create a classroom', function () {
    $school = makeCanManageSchool();
    $student = makeCanManageUsr($school, makeCanManageRole('ELEVE', 'Élève'))->user;

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle piratée'])
        ->assertForbidden();
});

test('an administrateur cannot create a classroom', function () {
    // Administrateur = gestion plateforme (écoles, users, rôles, traductions),
    // pas d'autorité sur le contenu académique d'une école en particulier.
    $school = makeCanManageSchool();
    $admin = makeCanManageUsr($school, makeCanManageRole('ADMIN', 'Administrateur'))->user;

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle piratée'])
        ->assertForbidden();
});

test('a directeur cannot create a classroom', function () {
    $school = makeCanManageSchool();
    $directeur = makeCanManageUsr($school, makeCanManageRole('DIR', 'Directeur'))->user;

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle piratée'])
        ->assertForbidden();
});

test('a teacher can still create a grade (can-manage unchanged for grades)', function () {
    $school = makeCanManageSchool();
    $teacher = makeCanManageUsr($school, makeCanManageRole('PROF', 'Professeur'))->user;

    $course = \App\Models\Course::create(['school_id' => $school->id, 'name' => 'Cours', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $subject = \App\Models\Subject::create(['course_id' => $course->id, 'name' => 'Matière', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $studentUsr = makeCanManageUsr($school, makeCanManageRole('ELEVE', 'Élève'));
    $sectionUser = \App\Models\SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $studentUsr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($teacher)->withSession(['active_school_id' => $school->id])
        ->post('/grades', [
            'section_user_id' => $sectionUser->id,
            'subject_id' => $subject->id,
            'period' => 'Trimestre 1',
            'max_grade' => 20,
            'grade' => 15,
        ])
        ->assertRedirect();

    expect(\App\Models\Grade::where('section_user_id', $sectionUser->id)->exists())->toBeTrue();
});

test('a teacher sees the classrooms page as read-only (no manage actions in props path)', function () {
    $school = makeCanManageSchool();
    $teacher = makeCanManageUsr($school, makeCanManageRole('PROF', 'Professeur'))->user;

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get('/classrooms')
        ->assertOk();
});
