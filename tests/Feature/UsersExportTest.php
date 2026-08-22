<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeExportSchool(): School
{
    return School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeExportRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeExportUsr(School $school, Role $role, array $userAttrs = []): UserSchoolRole
{
    $user = User::factory()->create($userAttrs);

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('an admin can export the active schools users as csv', function () {
    $schoolA = makeExportSchool();
    $schoolB = makeExportSchool();
    $admin = makeExportUsr($schoolA, makeExportRole('ADMIN', 'Administrateur'), [
        'firstname' => 'Alice', 'lastname' => 'Admin', 'email' => 'alice@example.test',
    ])->user;
    makeExportUsr($schoolA, makeExportRole('PROF', 'Professeur'), [
        'firstname' => 'Paul', 'lastname' => 'Prof', 'email' => 'paul@example.test',
    ]);
    makeExportUsr($schoolB, makeExportRole('POWER', 'Power User'), [
        'firstname' => 'Bob', 'lastname' => 'AutreEcole', 'email' => 'bob@example.test',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get('/users/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Paul')
        ->toContain('paul@example.test')
        ->toContain('Alice')
        ->not->toContain('bob@example.test');
});

test('a non-admin cannot export users as csv', function () {
    $school = makeExportSchool();
    $teacher = makeExportUsr($school, makeExportRole('PROF', 'Professeur'))->user;

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->get('/users/export')
        ->assertForbidden();
});
