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

test('a teacher can read the classroom list and a single classroom', function () {
    $school = makeCanManageSchool();
    $teacher = makeCanManageUsr($school, makeCanManageRole('PROF', 'Professeur'))->user;

    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle 1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get('/classrooms')
        ->assertOk();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get("/classrooms/{$classroom->id}")
        ->assertOk();
});

test('a teacher cannot create, edit or delete a classroom', function () {
    $school = makeCanManageSchool();
    $teacher = makeCanManageUsr($school, makeCanManageRole('PROF', 'Professeur'))->user;

    $classroom = Classroom::create([
        'school_id' => $school->id, 'name' => 'Salle 1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get('/classrooms/create')
        ->assertForbidden();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle piratée'])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get("/classrooms/{$classroom->id}/edit")
        ->assertForbidden();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->patch("/classrooms/{$classroom->id}", ['name' => 'Renommée'])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/classrooms/{$classroom->id}")
        ->assertForbidden();

    expect($classroom->refresh()->name)->toBe('Salle 1');
});

test('a student cannot create a classroom', function () {
    $school = makeCanManageSchool();
    $student = makeCanManageUsr($school, makeCanManageRole('ELEVE', 'Élève'))->user;

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle piratée'])
        ->assertForbidden();
});

test('a power user can still create a classroom', function () {
    $school = makeCanManageSchool();
    $powerUser = makeCanManageUsr($school, makeCanManageRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle 2'])
        ->assertRedirect();

    expect(Classroom::where('name', 'Salle 2')->exists())->toBeTrue();
});

test('a directeur can still create a classroom', function () {
    $school = makeCanManageSchool();
    $directeur = makeCanManageUsr($school, makeCanManageRole('DIR', 'Directeur'))->user;

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post('/classrooms', ['name' => 'Salle 3'])
        ->assertRedirect();

    expect(Classroom::where('name', 'Salle 3')->exists())->toBeTrue();
});
