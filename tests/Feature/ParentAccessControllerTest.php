<?php

use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makePacSchool(): School
{
    return School::create(['name' => 'École PAC '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makePacRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('a parent can activate one of their linked children', function () {
    $school = makePacSchool();
    $studentA = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePacRole('ELEVE', 'Élève')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $studentB = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePacRole('ELEVE', 'Élève')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create(['user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makePacRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentA->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $linkB = ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $studentB->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->post('/my-children/activate', ['link_id' => $linkB->id])
        ->assertRedirect();

    expect(session('active_child_link_id'))->toBe($linkB->id);
});

test('a parent cannot activate a link belonging to another parent', function () {
    $school = makePacSchool();
    $studentA = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePacRole('ELEVE', 'Élève')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherParentUsr = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePacRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $foreignLink = ParentStudentLink::create(['parent_user_school_role_id' => $otherParentUsr->id, 'student_user_school_role_id' => $studentA->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $parent = User::factory()->create();
    UserSchoolRole::create(['user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makePacRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->post('/my-children/activate', ['link_id' => $foreignLink->id])
        ->assertForbidden();
});

test('myChildren shared prop lists all active links for a parent', function () {
    $school = makePacSchool();
    $student = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePacRole('ELEVE', 'Élève')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create(['user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makePacRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $response = $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('hasParentAccess', true)
        ->has('myChildren', 1)
    );
});
