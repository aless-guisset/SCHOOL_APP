<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeScopedSchool(): School
{
    return School::create(['name' => 'École Scoped '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeScopedRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('scopedUserSchoolRole returns own row for a non-parent role', function () {
    $school = makeScopedSchool();
    $eleveRole = makeScopedRole('ELEVE', 'Élève');
    $eleve = User::factory()->create();
    $usr = UserSchoolRole::create([
        'user_id' => $eleve->id, 'school_id' => $school->id, 'role_id' => $eleveRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $result = $eleve->scopedUserSchoolRole($school->id);

    expect($result->id)->toBe($usr->id);
});

test('scopedUserSchoolRole returns the linked child row for a parent role', function () {
    $school = makeScopedSchool();
    $eleveRole = makeScopedRole('ELEVE', 'Élève');
    $parentRole = makeScopedRole('PARENT', 'Parent');
    $eleve = User::factory()->create();
    $studentUsr = UserSchoolRole::create([
        'user_id' => $eleve->id, 'school_id' => $school->id, 'role_id' => $eleveRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $parent = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'linked_student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $result = $parent->scopedUserSchoolRole($school->id);

    expect($result->id)->toBe($studentUsr->id);
});

test('scopedUserSchoolRole prefers the caller\'s own non-parent row when they also hold a parent row', function () {
    $school = makeScopedSchool();
    $eleveRole = makeScopedRole('ELEVE', 'Élève');
    $parentRole = makeScopedRole('PARENT', 'Parent');
    $profRole = makeScopedRole('PROF', 'Professeur');

    $eleve = User::factory()->create();
    $studentUsr = UserSchoolRole::create([
        'user_id' => $eleve->id, 'school_id' => $school->id, 'role_id' => $eleveRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // Ligne PARENT créée en premier : un `first()` non ordonné la retournerait.
    $prof = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $prof->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'linked_student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $profUsr = UserSchoolRole::create([
        'user_id' => $prof->id, 'school_id' => $school->id, 'role_id' => $profRole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($prof->scopedUserSchoolRole($school->id)->id)->toBe($profUsr->id)
        ->and($prof->activeRoleAt($school->id))->toBe('Professeur');
});

test('activeRoleAt reports Parent for an account whose only active row is a parent row', function () {
    $school = makeScopedSchool();
    $parent = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeScopedRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($parent->activeRoleAt($school->id))->toBe('Parent');
});

test('scopedUserSchoolRole returns null when the linked child has been deactivated', function () {
    $school = makeScopedSchool();
    $eleveRole = makeScopedRole('ELEVE', 'Élève');
    $parentRole = makeScopedRole('PARENT', 'Parent');
    $eleve = User::factory()->create();
    $studentUsr = UserSchoolRole::create([
        'user_id' => $eleve->id, 'school_id' => $school->id, 'role_id' => $eleveRole->id,
        'status' => 'A', 'is_active' => false, 'created_by' => 1,
    ]);
    $parent = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => $parentRole->id,
        'linked_student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($parent->scopedUserSchoolRole($school->id))->toBeNull();
});
