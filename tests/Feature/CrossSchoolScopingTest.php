<?php

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Resource;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\Subject;
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

test('a power user cannot view another schools course by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseB = Course::create([
        'school_id' => $schoolB->id, 'name' => 'Maths B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/courses/{$courseB->id}")
        ->assertNotFound();
});

test('a power user cannot view another schools section by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $sectionB = Section::create([
        'school_id' => $schoolB->id, 'name' => 'Classe B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/sections/{$sectionB->id}")
        ->assertNotFound();
});

test('a power user cannot view another schools lesson by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $courseB = Course::create([
        'school_id' => $schoolB->id, 'name' => 'Maths B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $subjectB = Subject::create([
        'course_id' => $courseB->id, 'name' => 'Algèbre B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $lessonB = Lesson::create([
        'school_id' => $schoolB->id, 'subject_id' => $subjectB->id, 'name' => 'Leçon B1',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/lessons/{$lessonB->id}")
        ->assertNotFound();
});

test('creating a resource persists its school_id instead of crashing', function () {
    $schoolA = makeScopingSchool('École A');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $response = $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/resources', ['name' => 'Vidéoprojecteur']);

    $response->assertRedirect();
    $resource = Resource::where('name', 'Vidéoprojecteur')->firstOrFail();
    expect($resource->school_id)->toBe($schoolA->id);
});

test('a power user cannot view another schools resource by guessing its id', function () {
    $schoolA = makeScopingSchool('École A');
    $schoolB = makeScopingSchool('École B');
    $powerUserA = makeScopingUsr($schoolA, makeScopingRole('POWER', 'Power User'))->user;

    $resourceB = Resource::create([
        'school_id' => $schoolB->id, 'name' => 'Imprimante B',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->get("/resources/{$resourceB->id}")
        ->assertNotFound();
});
