<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeApiAuthSchool(): School
{
    return School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeApiAuthRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeApiAuthUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('a student cannot list users via the api', function () {
    $school = makeApiAuthSchool();
    $student = makeApiAuthUsr($school, makeApiAuthRole('ELEVE', 'Élève'))->user;

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->getJson('/api/users')
        ->assertForbidden();
});

test('a student cannot create a role via the api', function () {
    $school = makeApiAuthSchool();
    $student = makeApiAuthUsr($school, makeApiAuthRole('ELEVE', 'Élève'))->user;

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->postJson('/api/roles', ['name' => 'Rôle piraté'])
        ->assertForbidden();

    expect(Role::where('name', 'Rôle piraté')->exists())->toBeFalse();
});

test('an admin can list users via the api', function () {
    $school = makeApiAuthSchool();
    $admin = makeApiAuthUsr($school, makeApiAuthRole('ADMIN', 'Administrateur'))->user;

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $school->id])
        ->getJson('/api/users')
        ->assertOk();
});

test('a teacher cannot create a classroom via the api but can read it', function () {
    $school = makeApiAuthSchool();
    $teacher = makeApiAuthUsr($school, makeApiAuthRole('PROF', 'Professeur'))->user;

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->postJson('/api/classrooms', ['name' => 'Salle piratée'])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->getJson('/api/classrooms')
        ->assertOk();
});

test('a power user is not blocked by authorization when creating a classroom via the api', function () {
    // ClassroomsController::store() is shared with the Inertia web routes and always
    // redirects rather than returning JSON — this test only proves the can-manage gate
    // let the request through (not forbidden), not that the API responds RESTfully.
    $school = makeApiAuthSchool();
    $powerUser = makeApiAuthUsr($school, makeApiAuthRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/api/classrooms', ['name' => 'Salle 1'])
        ->assertRedirect();

    expect(\App\Models\Classroom::where('name', 'Salle 1')->exists())->toBeTrue();
});
