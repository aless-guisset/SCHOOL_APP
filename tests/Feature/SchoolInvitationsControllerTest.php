<?php

use App\Models\Role;
use App\Models\School;
use App\Models\SchoolInvitation;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeInvSchool(): School
{
    return School::create(['name' => 'École Invit '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeInvRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeInvDirecteur(School $school): User
{
    $role = makeInvRole('DIR', 'Directeur');
    $directeur = User::factory()->create();
    UserSchoolRole::create(['user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return $directeur;
}

test('Directeur can send an invitation restricted to PROF/SEC/POWER, never ELEVE/DIR/ADMIN', function () {
    $school = makeInvSchool();
    $directeur = makeInvDirecteur($school);
    makeInvRole('SEC', 'Secrétariat');

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post('/invitations', ['email' => 'nouveau@example.com', 'role_reference' => 'SEC'])
        ->assertRedirect();

    expect(SchoolInvitation::where('email', 'nouveau@example.com')->count())->toBe(1);

    makeInvRole('ELEVE', 'Élève');
    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post('/invitations', ['email' => 'eleve@example.com', 'role_reference' => 'ELEVE'])
        ->assertSessionHasErrors('role_reference');
});

test('accepting a valid invitation for a new email creates the account and grants active access', function () {
    $school = makeInvSchool();
    $directeur = makeInvDirecteur($school);
    $role = makeInvRole('PROF', 'Professeur');
    $invitation = SchoolInvitation::create([
        'school_id' => $school->id, 'email' => 'prof@example.com', 'role_id' => $role->id,
        'token' => 'validtoken123', 'expires_at' => now()->addDays(7), 'is_active' => true, 'created_by' => $directeur->id,
    ]);

    $this->get('/invitations/validtoken123/accept')->assertOk();

    $this->post('/invitations/validtoken123/accept', [
        'firstname' => 'Jean', 'lastname' => 'Prof', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect();

    $user = User::where('email', 'prof@example.com')->first();
    expect($user)->not->toBeNull();
    $usr = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($usr->status)->toBe('A')->and($usr->role->reference)->toBe('PROF');
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('accepting a valid invitation for an existing account attaches the role without re-validating credentials', function () {
    $school = makeInvSchool();
    $directeur = makeInvDirecteur($school);
    $role = makeInvRole('PROF', 'Professeur');
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = SchoolInvitation::create([
        'school_id' => $school->id, 'email' => 'existing@example.com', 'role_id' => $role->id,
        'token' => 'existingtoken', 'expires_at' => now()->addDays(7), 'is_active' => true, 'created_by' => $directeur->id,
    ]);

    $this->post('/invitations/existingtoken/accept', [])->assertRedirect();

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);

    $usr = UserSchoolRole::where('user_id', $existingUser->id)->where('school_id', $school->id)->first();
    expect($usr)->not->toBeNull()
        ->and($usr->status)->toBe('A')
        ->and($usr->role->reference)->toBe('PROF');
    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($existingUser);
});

test('an expired invitation cannot be accepted', function () {
    $school = makeInvSchool();
    $directeur = makeInvDirecteur($school);
    $role = makeInvRole('PROF', 'Professeur');
    SchoolInvitation::create([
        'school_id' => $school->id, 'email' => 'expired@example.com', 'role_id' => $role->id,
        'token' => 'expiredtoken', 'expires_at' => now()->subDay(), 'is_active' => true, 'created_by' => $directeur->id,
    ]);

    $this->get('/invitations/expiredtoken/accept')->assertNotFound();
});

test('an already-accepted invitation cannot be reused', function () {
    $school = makeInvSchool();
    $directeur = makeInvDirecteur($school);
    $role = makeInvRole('PROF', 'Professeur');
    SchoolInvitation::create([
        'school_id' => $school->id, 'email' => 'used@example.com', 'role_id' => $role->id,
        'token' => 'usedtoken', 'expires_at' => now()->addDays(7), 'accepted_at' => now(), 'is_active' => true, 'created_by' => $directeur->id,
    ]);

    $this->get('/invitations/usedtoken/accept')->assertNotFound();
});
