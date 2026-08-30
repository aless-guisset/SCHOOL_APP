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
        ->and($link->status)->toBe('A')
        ->and(\App\Models\ParentStudentLink::where('parent_user_school_role_id', $link->id)->where('student_user_school_role_id', $studentUsr->id)->exists())->toBeTrue();
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

test('a parent account already linked to one child can join a second, different child', function () {
    $school = makeSauSchool();
    $studentA = makeSauStudent($school);
    $studentB = UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id,
        'role_id' => makeSauRole('ELEVE', 'Élève')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1, 'student_access_code' => 'STUCODE2',
    ]);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeSauRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentA->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $response = $this->actingAs($parent)->post('/join/parent', ['access_code' => 'STUCODE2']);

    $response->assertRedirect(route('dashboard'));
    expect(\App\Models\ParentStudentLink::where('parent_user_school_role_id', $parentUsr->id)->count())->toBe(2);
});

test('joining with the same student twice stays idempotent, no duplicate link', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    makeSauRole('PARENT', 'Parent');
    $parent = User::factory()->create();

    $this->actingAs($parent)->post('/join/parent', ['access_code' => 'STUCODE1']);
    $this->actingAs($parent)->post('/join/parent', ['access_code' => 'STUCODE1']);

    $parentUsr = UserSchoolRole::where('user_id', $parent->id)->where('school_id', $school->id)->first();
    expect(\App\Models\ParentStudentLink::where('parent_user_school_role_id', $parentUsr->id)->count())->toBe(1);
});

test('a student with a single school reaches /my-access on a fresh session with no active_school_id pre-set', function () {
    // /my-access est dans le groupe school.context (pas hors-contexte comme
    // /school/create) précisément pour ce cas : sur une toute première
    // requête après connexion, active_school_id n'est pas encore en session.
    // CheckSchoolContext doit l'établir (redirection puis rendu réel) plutôt
    // que laisser requireOwnStudentRole() chercher school_id=null et 403 à tort.
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);

    $this->actingAs($studentUsr->user);

    $response = $this->get('/my-access');
    $response->assertRedirect('/my-access');

    $response = $this->get('/my-access');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('student/GiveAccess')
        ->where('access_code', 'STUCODE1')
    );
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
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $link = \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/my-access/parents/{$link->id}")
        ->assertRedirect();

    expect(\App\Models\ParentStudentLink::find($link->id))->toBeNull();
});

test('a parent role recovers from a previous soft-delete instead of erroring', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    $parentRole = makeSauRole('PARENT', 'Parent');
    $parent = User::factory()->create();
    $old = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
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
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $link = \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $directeur = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => makeSauRole('DIR', 'Directeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/my-access/parents/{$link->id}")
        ->assertRedirect();

    expect(\App\Models\ParentStudentLink::find($link->id))->toBeNull();
});

test('a Directeur from another school cannot revoke a parent link', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeSauRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $link = \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $otherSchool = makeSauSchool();
    $otherDirecteur = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $otherDirecteur->id, 'school_id' => $otherSchool->id, 'role_id' => makeSauRole('DIR', 'Directeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($otherDirecteur)
        ->withSession(['active_school_id' => $otherSchool->id])
        ->delete("/my-access/parents/{$link->id}")
        ->assertForbidden();

    expect(\App\Models\ParentStudentLink::find($link->id))->not->toBeNull();
});

test('revoking the last link of a parent also deactivates their Parent role', function () {
    $school = makeSauSchool();
    $studentUsr = makeSauStudent($school);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeSauRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $link = \App\Models\ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/my-access/parents/{$link->id}")
        ->assertRedirect();

    expect($parentUsr->fresh()->status)->toBe('R');
});
