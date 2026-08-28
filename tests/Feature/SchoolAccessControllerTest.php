<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeAccessSchool(?string $code = null): School
{
    return School::create([
        'name' => 'École Accès '.uniqid(), 'status' => 'A', 'is_active' => true,
        'access_code' => $code, 'created_by' => 1,
    ]);
}

function makeAccessRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

beforeEach(function () {
    makeAccessRole('DIR', 'Directeur');
    makeAccessRole('ADMIN', 'Administrateur');
});

test('joining with a valid code and an allowed role grants immediate active access', function () {
    $school = makeAccessSchool('ABCD1234');
    makeAccessRole('PROF', 'Professeur');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/join/with-code', [
        'access_code' => 'ABCD1234', 'role_reference' => 'PROF',
    ])->assertRedirect();

    $usr = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($usr)->not->toBeNull()
        ->and($usr->status)->toBe('A')
        ->and($usr->role->reference)->toBe('PROF');
});

test('joining with an invalid code fails with a generic error, no user_school_role created', function () {
    makeAccessRole('PROF', 'Professeur');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/join/with-code', [
        'access_code' => 'DOESNOTEXIST', 'role_reference' => 'PROF',
    ])->assertSessionHasErrors('access_code');

    expect(UserSchoolRole::where('user_id', $user->id)->count())->toBe(0);
});

test('joining with a code cannot self-assign Directeur or Administrateur', function () {
    $school = makeAccessSchool('WXYZ5678');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/join/with-code', [
        'access_code' => 'WXYZ5678', 'role_reference' => 'DIR',
    ])->assertSessionHasErrors('role_reference');

    $this->actingAs($user)->post('/join/with-code', [
        'access_code' => 'WXYZ5678', 'role_reference' => 'ADMIN',
    ])->assertSessionHasErrors('role_reference');

    expect(UserSchoolRole::where('user_id', $user->id)->count())->toBe(0);
});

test('school search returns only active schools matching the query, id and name only', function () {
    makeAccessSchool()->update(['name' => 'Lycée Victor Hugo']);
    $pending = School::create(['name' => 'Lycée En Attente', 'status' => 'P', 'is_active' => false, 'created_by' => 1]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/schools/search?q=Victor');

    $response->assertOk();
    $results = $response->json();
    expect($results)->toHaveCount(1)
        ->and($results[0])->toHaveKeys(['id', 'name'])
        ->and(collect($results)->pluck('name')->contains('Lycée En Attente'))->toBeFalse();
});
