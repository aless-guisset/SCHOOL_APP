<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeSauSchool(): School
{
    return School::create(['name' => 'École SAU '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeSauRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeSauStudent(School $school): UserSchoolRole
{
    $eleve = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $eleve->id, 'school_id' => $school->id, 'role_id' => makeSauRole('ELEVE', 'Élève')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
        'student_access_code' => 'STUCODE1',
    ]);
}

test('an anonymous visitor can join with a valid student code and ends up authenticated as Parent', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    makeSauRole('PARENT', 'Parent');

    $response = $this->post('/join/parent', [
        'access_code' => 'STUCODE1',
        'firstname' => 'Jean', 'lastname' => 'Parent', 'email' => 'jean.parent@example.com',
        'password' => 'MotDePasse123!Fort', 'password_confirmation' => 'MotDePasse123!Fort',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $parent = User::where('email', 'jean.parent@example.com')->first();
    $link = UserSchoolRole::where('user_id', $parent->id)->where('school_id', $school->id)->first();
    expect($link->role->reference)->toBe('PARENT')
        ->and($link->linked_student_user_school_role_id)->toBe($studentUsr->id)
        ->and($link->status)->toBe('A');
});

test('joining with an invalid student code fails with a generic error', function () {
    makeSauSchool();

    $response = $this->post('/join/parent', [
        'access_code' => 'NOPE0000',
        'firstname' => 'Jean', 'lastname' => 'Parent', 'email' => 'jean2@example.com',
        'password' => 'MotDePasse123!Fort', 'password_confirmation' => 'MotDePasse123!Fort',
    ]);

    $response->assertSessionHasErrors('access_code');
    $this->assertGuest();
});

test('a parent account already linked to a different child cannot join a second student', function () {
    $school = makeSauSchool();
    $studentA = makeSauStudent($school);
    $studentB = UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id,
        'role_id' => makeSauRole('ELEVE', 'Élève')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1, 'student_access_code' => 'STUCODE2',
    ]);
    $parent = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeSauRole('PARENT', 'Parent')->id,
        'linked_student_user_school_role_id' => $studentA->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $response = $this->actingAs($parent)->post('/join/parent', ['access_code' => 'STUCODE2']);

    $response->assertSessionHasErrors('access_code');
    expect(UserSchoolRole::where('user_id', $parent->id)->where('linked_student_user_school_role_id', $studentB->id)->exists())->toBeFalse();
});

test('the student can regenerate their access code, and the old code stops working', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post('/my-access/regenerate-code')
        ->assertRedirect();

    $studentUsr->refresh();
    expect($studentUsr->student_access_code)->not->toBe('STUCODE1');

    $this->post('/join/parent', [
        'access_code' => 'STUCODE1',
        'firstname' => 'X', 'lastname' => 'Y', 'email' => 'old-code@example.com',
        'password' => 'MotDePasse123!Fort', 'password_confirmation' => 'MotDePasse123!Fort',
    ])->assertSessionHasErrors('access_code');
});

test('the student can revoke a linked parent', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeSauRole('PARENT', 'Parent')->id,
        'linked_student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/my-access/parents/{$parentUsr->id}")
        ->assertRedirect();

    expect(UserSchoolRole::find($parentUsr->id))->toBeNull();
});

test('a parent role recovers from a previous soft-delete instead of erroring', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    $parentRole = makeSauRole('PARENT', 'Parent');
    $parent = User::factory()->create();
    $old = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'linked_student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => false, 'created_by' => 1,
    ]);
    $old->delete();

    $response = $this->actingAs($parent)->post('/join/parent', ['access_code' => 'STUCODE1']);

    $response->assertRedirect(route('dashboard'));
    $link = UserSchoolRole::where('user_id', $parent->id)->where('school_id', $school->id)->first();
    expect($link->trashed())->toBeFalse()->and($link->status)->toBe('A');
});

test('a Directeur can revoke a linked parent at their school', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeSauRole('PARENT', 'Parent')->id,
        'linked_student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $directeur = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => makeSauRole('DIR', 'Directeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/my-access/parents/{$parentUsr->id}")
        ->assertRedirect();

    expect(UserSchoolRole::find($parentUsr->id))->toBeNull();
});

test('a Directeur cannot revoke a non-Parent user_school_role through this endpoint', function () {
    $school = makeSauSchool();
    $professeur = User::factory()->create();
    $professeurUsr = UserSchoolRole::create([
        'user_id' => $professeur->id, 'school_id' => $school->id, 'role_id' => makeSauRole('PROF', 'Professeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $directeur = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => makeSauRole('DIR', 'Directeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/my-access/parents/{$professeurUsr->id}")
        ->assertForbidden();

    expect(UserSchoolRole::find($professeurUsr->id))->not->toBeNull();
});
