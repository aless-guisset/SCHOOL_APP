<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use Inertia\Testing\AssertableInertia as Assert;

function makePendingScopingSchool(): School
{
    return School::create(['name' => 'École Scoping', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makePendingScopingRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('a user_school_role with status=P does not grant currentRole/canManage', function () {
    $school = makePendingScopingSchool();
    $role = makePendingScopingRole('PROF', 'Professeur');
    $user = User::factory()->create();
    $usr = UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'P', 'is_active' => true, 'created_by' => 1]);

    $response = $this->actingAs($user)
        ->withSession(['active_school_id' => $school->id])
        ->get('/dashboard');

    // school.context (CheckSchoolContext) doit traiter ce compte comme "0 école active"
    // et donc rediriger ailleurs plutôt que de laisser passer vers /dashboard avec le rôle P.
    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('/dashboard');
});

test('a user_school_role with status=A grants access normally', function () {
    $school = makePendingScopingSchool();
    $role = makePendingScopingRole('PROF', 'Professeur');
    $user = User::factory()->create();
    UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $school->id])
        ->get('/dashboard')
        ->assertOk();
});

test('director-only middleware allows Directeur and blocks everyone else', function () {
    $school = makePendingScopingSchool();
    $dirRole = makePendingScopingRole('DIR', 'Directeur');
    $profRole = makePendingScopingRole('PROF', 'Professeur');

    $directeur = User::factory()->create();
    UserSchoolRole::create(['user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => $dirRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $prof = User::factory()->create();
    UserSchoolRole::create(['user_id' => $prof->id, 'school_id' => $school->id, 'role_id' => $profRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    Route::middleware(['web', 'auth', 'director-only'])->get('/__test-director-only', fn () => 'ok');

    $this->actingAs($directeur)->withSession(['active_school_id' => $school->id])
        ->get('/__test-director-only')->assertOk();

    $this->actingAs($prof)->withSession(['active_school_id' => $school->id])
        ->get('/__test-director-only')->assertForbidden();
});

test('select() shows the status=A role, never a pending/rejected one, when a user has two rows at the same school', function () {
    $school = makePendingScopingSchool();
    $dirRole = makePendingScopingRole('DIR', 'Directeur');
    $profRole = makePendingScopingRole('PROF', 'Professeur');
    $user = User::factory()->create();

    // Pending row created first (lower id) so a naive `->first()` without a
    // `status='A'` filter would surface this role instead of the active one.
    UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $dirRole->id, 'status' => 'P', 'is_active' => true, 'created_by' => 1]);
    UserSchoolRole::create(['user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $profRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($user)
        ->get('/school/select')
        ->assertInertia(fn (Assert $page) => $page
            ->component('school/Select')
            ->has('schools', 1)
            ->where('schools.0.id', $school->id)
            ->where('schools.0.role', 'Professeur')
        );
});
