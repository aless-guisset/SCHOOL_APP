<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeReqSchool(): School
{
    return School::create(['name' => 'École Demande '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeReqRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeReqDirecteur(School $school): User
{
    $role = makeReqRole('DIR', 'Directeur');
    $directeur = User::factory()->create();
    UserSchoolRole::create(['user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return $directeur;
}

test('a student joining without a code always gets status=P, role forced to ELEVE server-side', function () {
    $school = makeReqSchool();
    makeReqRole('ELEVE', 'Élève');
    $user = User::factory()->create(['profile' => 'student']);

    // role_reference falsifié à PROF depuis le client — doit être ignoré, forcé à ELEVE.
    $this->actingAs($user)->post('/join/request', [
        'school_id' => $school->id, 'role_reference' => 'PROF', 'is_student' => true,
    ])->assertRedirect();

    $usr = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($usr->status)->toBe('P')
        ->and($usr->role->reference)->toBe('ELEVE');
});

test('a staff request without a code gets status=P with the chosen role', function () {
    $school = makeReqSchool();
    makeReqRole('SEC', 'Secrétariat');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/join/request', [
        'school_id' => $school->id, 'role_reference' => 'SEC', 'is_student' => false,
    ])->assertRedirect();

    $usr = UserSchoolRole::where('user_id', $user->id)->where('school_id', $school->id)->first();
    expect($usr->status)->toBe('P')->and($usr->role->reference)->toBe('SEC');
});

test('a P-status request is invisible in access-requests index for a different school Directeur', function () {
    $schoolA = makeReqSchool();
    $schoolB = makeReqSchool();
    makeReqRole('PROF', 'Professeur');
    $directeurB = makeReqDirecteur($schoolB);
    $requester = User::factory()->create();
    $this->actingAs($requester)->post('/join/request', ['school_id' => $schoolA->id, 'role_reference' => 'PROF', 'is_student' => false]);

    $this->actingAs($directeurB)
        ->withSession(['active_school_id' => $schoolB->id])
        ->get('/access-requests')
        ->assertInertia(fn ($page) => $page->has('requests', 0));
});

test('Directeur can approve a pending request, which flips status to A', function () {
    $school = makeReqSchool();
    makeReqRole('PROF', 'Professeur');
    $directeur = makeReqDirecteur($school);
    $requester = User::factory()->create();
    $this->actingAs($requester)->post('/join/request', ['school_id' => $school->id, 'role_reference' => 'PROF', 'is_student' => false]);
    $usr = UserSchoolRole::where('user_id', $requester->id)->first();

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post("/access-requests/{$usr->id}/approve")
        ->assertRedirect();

    expect($usr->fresh()->status)->toBe('A');
});

test('Directeur can reject a pending request, which flips status to R and does not grant access', function () {
    $school = makeReqSchool();
    makeReqRole('PROF', 'Professeur');
    $directeur = makeReqDirecteur($school);
    $requester = User::factory()->create();
    $this->actingAs($requester)->post('/join/request', ['school_id' => $school->id, 'role_reference' => 'PROF', 'is_student' => false]);
    $usr = UserSchoolRole::where('user_id', $requester->id)->first();

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post("/access-requests/{$usr->id}/reject")
        ->assertRedirect();

    expect($usr->fresh()->status)->toBe('R');
});

test('a non-Directeur cannot approve requests, even Power User', function () {
    $school = makeReqSchool();
    makeReqRole('PROF', 'Professeur');
    $powerRole = makeReqRole('POWER', 'Power User');
    $power = User::factory()->create();
    UserSchoolRole::create(['user_id' => $power->id, 'school_id' => $school->id, 'role_id' => $powerRole->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $requester = User::factory()->create();
    $this->actingAs($requester)->post('/join/request', ['school_id' => $school->id, 'role_reference' => 'PROF', 'is_student' => false]);
    $usr = UserSchoolRole::where('user_id', $requester->id)->first();

    $this->actingAs($power)
        ->withSession(['active_school_id' => $school->id])
        ->post("/access-requests/{$usr->id}/approve")
        ->assertForbidden();
});
