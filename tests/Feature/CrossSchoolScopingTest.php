<?php

use App\Models\Classroom;
use App\Models\Role;
use App\Models\School;
use App\Models\UserSchoolRole;

function makeScopingSchool(string $name = 'École A'): School
{
    return School::create([
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeScopingRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeScopingUsr(School $school, Role $role): UserSchoolRole
{
    $user = \App\Models\User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('a power user cannot view another schools classroom by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerRole = makeScopingRole('POWER', 'Power User');
    $powerUserA = makeScopingUsr($schoolA, $powerRole)->user;

    $classroomB = Classroom::create([
        'school_id' => $schoolB->id, 'name' => 'Salle B1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/classrooms/{$classroomB->id}")
        ->assertNotFound();
});

test('a power user can still view their own schools classroom', function () {
    $schoolA = makeScopingSchool('École A');
    $powerRole = makeScopingRole('POWER', 'Power User');
    $powerUserA = makeScopingUsr($schoolA, $powerRole)->user;

    $classroomA = Classroom::create([
        'school_id' => $schoolA->id, 'name' => 'Salle A1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/classrooms/{$classroomA->id}")
        ->assertOk();
});
