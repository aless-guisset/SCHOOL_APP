<?php

use App\Models\Role;
use App\Models\School;
use App\Models\StudentInvitation;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeSicSchool(): School
{
    return School::create(['name' => 'École SIC '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeSicRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeSicStudent(School $school): UserSchoolRole
{
    $eleve = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $eleve->id, 'school_id' => $school->id, 'role_id' => makeSicRole('ELEVE', 'Élève')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('a student can invite a parent by email', function () {
    $school = makeSicSchool();
    $studentUsr = makeSicStudent($school);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->post('/my-access/invitations', ['email' => 'parent@example.com'])
        ->assertRedirect();

    expect(StudentInvitation::where('student_user_school_role_id', $studentUsr->id)->where('email', 'parent@example.com')->exists())->toBeTrue();
});

test('sending a new invitation to the same email cancels the previous one', function () {
    $school = makeSicSchool();
    $studentUsr = makeSicStudent($school);
    $jar = $this->actingAs($studentUsr->user)->withSession(['active_school_id' => $school->id]);

    $jar->post('/my-access/invitations', ['email' => 'parent@example.com']);
    $first = StudentInvitation::where('email', 'parent@example.com')->first();

    $jar->post('/my-access/invitations', ['email' => 'parent@example.com']);

    expect(StudentInvitation::find($first->id))->toBeNull()
        ->and(StudentInvitation::where('email', 'parent@example.com')->count())->toBe(1);
});

test('accepting an invitation creates an account and grants the Parent role', function () {
    $school = makeSicSchool();
    $studentUsr = makeSicStudent($school);
    makeSicRole('PARENT', 'Parent');
    $invitation = StudentInvitation::create([
        'student_user_school_role_id' => $studentUsr->id, 'email' => 'invite-accept@example.com',
        'token' => 'test-token-123', 'expires_at' => now()->addDays(7), 'is_active' => true, 'created_by' => $studentUsr->user_id,
    ]);

    $response = $this->post("/invitations/student/{$invitation->token}/accept", [
        'firstname' => 'Marie', 'lastname' => 'Parent',
        'password' => 'MotDePasse123!Fort', 'password_confirmation' => 'MotDePasse123!Fort',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();

    $parent = User::where('email', 'invite-accept@example.com')->first();
    $link = UserSchoolRole::where('user_id', $parent->id)->first();
    expect($link->role->reference)->toBe('PARENT')
        ->and($link->linked_student_user_school_role_id)->toBe($studentUsr->id);
});

test('accepting an invitation with an account already linked to another child fails as a field error, not a 422 page', function () {
    $school = makeSicSchool();
    $studentA = makeSicStudent($school);
    $studentB = makeSicStudent($school);
    $parentRole = makeSicRole('PARENT', 'Parent');

    $parent = User::factory()->create(['email' => 'deja-lie@example.com']);
    UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'linked_student_user_school_role_id' => $studentA->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $invitation = StudentInvitation::create([
        'student_user_school_role_id' => $studentB->id, 'email' => 'deja-lie@example.com',
        'token' => 'already-linked-token', 'expires_at' => now()->addDays(7), 'is_active' => true, 'created_by' => $studentB->user_id,
    ]);

    $this->post("/invitations/student/{$invitation->token}/accept")
        ->assertSessionHasErrors('email');

    $invitation->refresh();
    expect($invitation->accepted_at)->toBeNull()
        ->and(UserSchoolRole::where('user_id', $parent->id)->where('linked_student_user_school_role_id', $studentB->id)->exists())->toBeFalse();
});

test('an expired invitation cannot be accepted', function () {
    $school = makeSicSchool();
    $studentUsr = makeSicStudent($school);
    $invitation = StudentInvitation::create([
        'student_user_school_role_id' => $studentUsr->id, 'email' => 'expired@example.com',
        'token' => 'expired-token', 'expires_at' => now()->subDay(), 'is_active' => true, 'created_by' => $studentUsr->user_id,
    ]);

    $this->post("/invitations/student/{$invitation->token}/accept", [
        'firstname' => 'X', 'lastname' => 'Y',
        'password' => 'MotDePasse123!Fort', 'password_confirmation' => 'MotDePasse123!Fort',
    ])->assertNotFound();
});

test('the student can cancel a pending invitation', function () {
    $school = makeSicSchool();
    $studentUsr = makeSicStudent($school);
    $invitation = StudentInvitation::create([
        'student_user_school_role_id' => $studentUsr->id, 'email' => 'cancel-me@example.com',
        'token' => 'cancel-token', 'expires_at' => now()->addDays(7), 'is_active' => true, 'created_by' => $studentUsr->user_id,
    ]);

    $this->actingAs($studentUsr->user)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/my-access/invitations/{$invitation->id}")
        ->assertRedirect();

    expect(StudentInvitation::find($invitation->id))->toBeNull();
});
