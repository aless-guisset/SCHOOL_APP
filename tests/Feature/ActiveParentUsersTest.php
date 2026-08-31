<?php

use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeApuSchool(): School
{
    return School::create(['name' => 'École APU '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeApuRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeApuStudent(School $school): UserSchoolRole
{
    return UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id,
        'role_id' => makeApuRole('ELEVE', 'Élève')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeApuParent(School $school, UserSchoolRole $student, string $linkStatus = 'A', bool $linkActive = true): User
{
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id,
        'role_id' => makeApuRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->id,
        'status' => $linkStatus, 'is_active' => $linkActive, 'created_by' => 1,
    ]);

    return $parent;
}

test('activeParentUsers returns an empty collection when the student has no linked parent', function () {
    $school = makeApuSchool();
    $student = makeApuStudent($school);

    expect($student->activeParentUsers())->toBeEmpty();
});

test('activeParentUsers returns the linked parent', function () {
    $school = makeApuSchool();
    $student = makeApuStudent($school);
    $parent = makeApuParent($school, $student);

    $result = $student->activeParentUsers();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($parent->id);
});

test('activeParentUsers returns multiple parents linked to the same student', function () {
    $school = makeApuSchool();
    $student = makeApuStudent($school);
    $parentA = makeApuParent($school, $student);
    $parentB = makeApuParent($school, $student);

    $result = $student->activeParentUsers();

    expect($result)->toHaveCount(2)
        ->and($result->pluck('id')->sort()->values()->all())
        ->toBe(collect([$parentA->id, $parentB->id])->sort()->values()->all());
});

test('activeParentUsers excludes a revoked link', function () {
    $school = makeApuSchool();
    $student = makeApuStudent($school);
    makeApuParent($school, $student, linkStatus: 'R', linkActive: false);

    expect($student->activeParentUsers())->toBeEmpty();
});

test('activeParentUsers does not return a parent linked to a different student', function () {
    $school = makeApuSchool();
    $student = makeApuStudent($school);
    $otherStudent = makeApuStudent($school);
    makeApuParent($school, $otherStudent);

    expect($student->activeParentUsers())->toBeEmpty();
});
