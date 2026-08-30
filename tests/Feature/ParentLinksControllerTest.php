<?php

use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makePlcSchool(): School
{
    return School::create(['name' => 'École PLC '.uniqid(), 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makePlcRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('director sees all active parent-student links at their school, scoped correctly', function () {
    $school = makePlcSchool();
    $otherSchool = makePlcSchool();

    $student = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePlcRole('ELEVE', 'Élève')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parentUsr = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePlcRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    // Lien d'une AUTRE école — ne doit jamais apparaître.
    $otherStudent = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $otherSchool->id, 'role_id' => makePlcRole('ELEVE', 'Élève')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $otherParentUsr = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $otherSchool->id, 'role_id' => makePlcRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    ParentStudentLink::create(['parent_user_school_role_id' => $otherParentUsr->id, 'student_user_school_role_id' => $otherStudent->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $directeur = User::factory()->create();
    UserSchoolRole::create(['user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => makePlcRole('DIR', 'Directeur')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $response = $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->get('/parent-links');

    $response->assertInertia(fn ($page) => $page
        ->component('director/web/ParentLinks/Index')
        ->has('links', 1)
    );
});

test('director can revoke a parent-student link from this page', function () {
    $school = makePlcSchool();
    $student = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePlcRole('ELEVE', 'Élève')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $parentUsr = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makePlcRole('PARENT', 'Parent')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $link = ParentStudentLink::create(['parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $directeur = User::factory()->create();
    UserSchoolRole::create(['user_id' => $directeur->id, 'school_id' => $school->id, 'role_id' => makePlcRole('DIR', 'Directeur')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/parent-links/{$link->id}")
        ->assertRedirect();

    expect(ParentStudentLink::find($link->id))->toBeNull();
});
