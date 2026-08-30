<?php

use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makePslSchool(): School
{
    return School::create(['name' => 'École PSL '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makePslRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makePslStudent(School $school): UserSchoolRole
{
    return UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id,
        'role_id' => makePslRole('ELEVE', 'Élève')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makePslParentLink(UserSchoolRole $parentUsr, UserSchoolRole $studentUsr): ParentStudentLink
{
    return ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('scopedUserSchoolRole resolves to the active child link, defaulting to the first if none selected', function () {
    $school = makePslSchool();
    $studentA = makePslStudent($school);
    $studentB = makePslStudent($school);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makePslRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    makePslParentLink($parentUsr, $studentA);
    $linkB = makePslParentLink($parentUsr, $studentB);

    expect($parent->scopedUserSchoolRole($school->id)->id)->toBe($studentA->id);

    session(['active_child_link_id' => $linkB->id]);
    expect($parent->scopedUserSchoolRole($school->id)->id)->toBe($studentB->id);
});

test('scopedUserSchoolRole still prioritizes the more privileged role over Parent (dual-role safety)', function () {
    $school = makePslSchool();
    $child = makePslStudent($school);
    $teacherParent = User::factory()->create();
    $profUsr = UserSchoolRole::create([
        'user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makePslRole('PROF', 'Professeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $parentUsr = UserSchoolRole::create([
        'user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makePslRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    makePslParentLink($parentUsr, $child);

    // scopedUserSchoolRole() reste la ligne Professeur (plus privilégiée) —
    // ne doit JAMAIS résoudre vers l'enfant ici, sous peine de casser le
    // dashboard "mes propres cours" du professeur.
    expect($teacherParent->scopedUserSchoolRole($school->id)->id)->toBe($profUsr->id);
});

test('parentLinkedStudent resolves the child regardless of the user\'s more privileged role', function () {
    $school = makePslSchool();
    $child = makePslStudent($school);
    $teacherParent = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makePslRole('PROF', 'Professeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $parentUsr = UserSchoolRole::create([
        'user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makePslRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    makePslParentLink($parentUsr, $child);

    expect($teacherParent->parentLinkedStudent($school->id)->id)->toBe($child->id);
});

test('parentLinkedStudent returns null when the Parent role exists but is revoked or deactivated', function () {
    $school = makePslSchool();
    $child = makePslStudent($school);
    $teacherParent = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makePslRole('PROF', 'Professeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $parentUsr = UserSchoolRole::create([
        'user_id' => $teacherParent->id, 'school_id' => $school->id, 'role_id' => makePslRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    makePslParentLink($parentUsr, $child);

    expect($teacherParent->parentLinkedStudent($school->id)->id)->toBe($child->id);

    // Rôle Parent révoqué (status='R') puis simplement désactivé : dans les
    // deux cas l'accès à l'enfant tombe, même si le lien parent↔élève, lui,
    // est resté actif — c'est la frontière de sécurité de as_parent=1.
    $parentUsr->update(['status' => 'R']);
    expect($teacherParent->parentLinkedStudent($school->id))->toBeNull();

    $parentUsr->update(['status' => 'A', 'is_active' => false]);
    expect($teacherParent->parentLinkedStudent($school->id))->toBeNull();
});

test('parentLinkedStudent returns null when the user has no active Parent role at this school', function () {
    $school = makePslSchool();
    $prof = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $prof->id, 'school_id' => $school->id, 'role_id' => makePslRole('PROF', 'Professeur')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($prof->parentLinkedStudent($school->id))->toBeNull();
});
