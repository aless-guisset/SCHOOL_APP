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

test('a Directeur can regenerate the access code, and the old code stops working immediately', function () {
    $school = makeAccessSchool('OLDCODE1');
    makeAccessRole('PROF', 'Professeur');
    $directeurRole = makeAccessRole('DIR', 'Directeur');
    $directeur = User::factory()->create();
    UserSchoolRole::create(['user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => $directeurRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post('/school/access-code/regenerate')
        ->assertRedirect();

    $newCode = $school->fresh()->access_code;
    expect($newCode)->not->toBeNull()->and($newCode)->not->toBe('OLDCODE1');

    // L'ancien code ne fonctionne plus, prouvant qu'il est réellement révoqué
    // (et pas simplement qu'un nouveau code coexiste avec lui).
    $joiner = User::factory()->create();
    $this->actingAs($joiner)->post('/join/with-code', [
        'access_code' => 'OLDCODE1', 'role_reference' => 'PROF',
    ])->assertSessionHasErrors('access_code');
    expect(UserSchoolRole::where('user_id', $joiner->id)->count())->toBe(0);
});

test('a non-Directeur cannot regenerate the access code, even Power User', function () {
    $school = makeAccessSchool('KEEPCODE');
    $powerRole = makeAccessRole('POWER', 'Power User');
    $power = User::factory()->create();
    UserSchoolRole::create(['user_id' => $power->id, 'school_id' => $school->id, 'role_id' => $powerRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->post('/school/access-code/regenerate')
        ->assertForbidden();

    expect($school->fresh()->access_code)->toBe('KEEPCODE');
});

test('an anonymous visitor can join with a code and ends up authenticated with an active role', function () {
    $school = makeAccessSchool('ANONCODE');
    makeAccessRole('PROF', 'Professeur');

    $this->post('/join/with-code', [
        'access_code' => 'ANONCODE', 'role_reference' => 'PROF',
        'firstname' => 'Nouveau', 'lastname' => 'Prof', 'email' => 'anon-code@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect();

    $user = User::where('email', 'anon-code@example.com')->first();
    expect($user)->not->toBeNull();
    $this->assertAuthenticatedAs($user);

    $usr = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($usr)->not->toBeNull()
        ->and($usr->status)->toBe('A')
        ->and($usr->role->reference)->toBe('PROF');
});

test('an anonymous visitor can submit a join request and ends up authenticated with a pending role', function () {
    $school = makeAccessSchool();
    makeAccessRole('PROF', 'Professeur');

    $this->post('/join/request', [
        'school_id' => $school->id, 'role_reference' => 'PROF', 'is_student' => false,
        'firstname' => 'Nouvelle', 'lastname' => 'Demande', 'email' => 'anon-request@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect(route('join.pending'));

    $user = User::where('email', 'anon-request@example.com')->first();
    expect($user)->not->toBeNull();
    $this->assertAuthenticatedAs($user);

    $usr = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($usr)->not->toBeNull()->and($usr->status)->toBe('P');
});

test('joining with code using an email that already has an account fails with a login-first error, no account takeover', function () {
    $school = makeAccessSchool('TAKEOVER');
    makeAccessRole('PROF', 'Professeur');
    $existing = User::factory()->create(['email' => 'already@example.com']);

    $this->post('/join/with-code', [
        'access_code' => 'TAKEOVER', 'role_reference' => 'PROF',
        'firstname' => 'Imposteur', 'lastname' => 'X', 'email' => 'already@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(UserSchoolRole::where('user_id', $existing->id)->count())->toBe(0);
    expect(User::where('email', 'already@example.com')->count())->toBe(1);
});

test('join request using an email that already has an account fails with a login-first error, no account takeover', function () {
    $school = makeAccessSchool();
    makeAccessRole('PROF', 'Professeur');
    $existing = User::factory()->create(['email' => 'already-req@example.com']);

    $this->post('/join/request', [
        'school_id' => $school->id, 'role_reference' => 'PROF', 'is_student' => false,
        'firstname' => 'Imposteur', 'lastname' => 'X', 'email' => 'already-req@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(UserSchoolRole::where('user_id', $existing->id)->count())->toBe(0);
});

test('an already-authenticated user joining a second school still works exactly as before', function () {
    $firstSchool = makeAccessSchool('FIRSTSCH1');
    $secondSchool = makeAccessSchool('SECONDSC1');
    makeAccessRole('PROF', 'Professeur');
    $user = User::factory()->create();
    UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $firstSchool->id, 'role_id' => makeAccessRole('PROF', 'Professeur')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($user)->post('/join/with-code', [
        'access_code' => 'SECONDSC1', 'role_reference' => 'PROF',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    $usr = UserSchoolRole::where('user_id', $user->id)->where('school_id', $secondSchool->id)->first();
    expect($usr)->not->toBeNull()->and($usr->status)->toBe('A');
});

test('joining with a code recovers a previously soft-deleted user_school_role instead of 500ing', function () {
    $school = makeAccessSchool('RESTORE1');
    $role = makeAccessRole('PROF', 'Professeur');
    $user = User::factory()->create();
    $row = UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $row->update(['is_active' => false]);
    $row->delete();
    expect($row->trashed())->toBeTrue();

    $this->actingAs($user)->post('/join/with-code', [
        'access_code' => 'RESTORE1', 'role_reference' => 'PROF',
    ])->assertRedirect();

    $fresh = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse()
        ->and($fresh->status)->toBe('A')
        ->and($fresh->is_active)->toBeTrue();
});

test('joining with a code recovers a rejected user_school_role back to active, rather than leaving it stale', function () {
    $school = makeAccessSchool('REJECTED1');
    $role = makeAccessRole('PROF', 'Professeur');
    $user = User::factory()->create();
    UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'R', 'is_active' => false, 'created_by' => 1]);

    $this->actingAs($user)->post('/join/with-code', [
        'access_code' => 'REJECTED1', 'role_reference' => 'PROF',
    ])->assertRedirect();

    $fresh = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($fresh->status)->toBe('A')->and($fresh->is_active)->toBeTrue();
});

test('a join request recovers a rejected user_school_role back to pending, rather than leaving it stale', function () {
    $school = makeAccessSchool();
    $role = makeAccessRole('PROF', 'Professeur');
    $user = User::factory()->create();
    UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'R', 'is_active' => false, 'created_by' => 1]);

    $this->actingAs($user)->post('/join/request', [
        'school_id' => $school->id, 'role_reference' => 'PROF', 'is_student' => false,
    ])->assertRedirect(route('join.pending'));

    $fresh = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($fresh->status)->toBe('P')->and($fresh->is_active)->toBeTrue();
});

test('access_code is never present in the school panel or school controller JSON payloads', function () {
    $school = makeAccessSchool('SECRET99');
    $role = makeAccessRole('PROF', 'Professeur');
    $user = User::factory()->create();
    UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $response = $this->actingAs($user)
        ->withSession(['active_school_id' => $school->id])
        ->get("/schools/{$school->id}/panel");
    $response->assertOk();
    expect($response->getContent())->not->toContain('SECRET99');
});
