<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeSubmitSchoolSchool(): School
{
    return School::create(['name' => 'École existante', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeSubmitSchoolRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeSubmitSchoolUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('un élève ne peut pas accéder au formulaire de soumission d\'école', function () {
    $school = makeSubmitSchoolSchool();
    $student = makeSubmitSchoolUsr($school, makeSubmitSchoolRole('ELEVE', 'Élève'))->user;

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->get('/school/create')
        ->assertForbidden();
});

test('un élève ne peut pas soumettre une école', function () {
    $school = makeSubmitSchoolSchool();
    $student = makeSubmitSchoolUsr($school, makeSubmitSchoolRole('ELEVE', 'Élève'))->user;

    $this->actingAs($student)
        ->withSession(['active_school_id' => $school->id])
        ->post('/school/create', ['name' => 'École piratée'])
        ->assertForbidden();

    expect(School::where('name', 'École piratée')->exists())->toBeFalse();
});

test('un power user peut accéder au formulaire de soumission d\'école', function () {
    $school = makeSubmitSchoolSchool();
    $powerUser = makeSubmitSchoolUsr($school, makeSubmitSchoolRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/school/create')
        ->assertOk();
});

test('un utilisateur sans école active peut soumettre une école (fondateur fraîchement inscrit)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/school/create')
        ->assertOk();
});
