<?php

use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Services\CantineStripeService;

function makeTopUpSchool(): School
{
    return School::create(['name' => 'École TopUp', 'status' => 'A', 'is_active' => true, 'cantine_enabled' => true, 'created_by' => 1]);
}

function makeTopUpRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeTopUpStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $usr = UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => makeTopUpRole('ELEVE', 'Élève')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $usr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeTopUpParent(School $school, SectionUserSchoolRole $student): User
{
    $parent = User::factory()->create();
    $parentUsr = UserSchoolRole::create([
        'user_id' => $parent->id, 'school_id' => $school->id, 'role_id' => makeTopUpRole('PARENT', 'Parent')->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    ParentStudentLink::create([
        'parent_user_school_role_id' => $parentUsr->id, 'student_user_school_role_id' => $student->userschoolrole->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return $parent;
}

test('a parent can start a top-up for their linked child', function () {
    $school = makeTopUpSchool();
    $student = makeTopUpStudent($school);
    $parent = makeTopUpParent($school, $student);

    $this->mock(CantineStripeService::class, function ($mock) {
        $mock->shouldReceive('createTopUpSession')->once()->andReturn('https://checkout.stripe.com/fake-session');
    });

    // X-Inertia : simule une requête XHR envoyée par le client Inertia — c'est
    // ce qui fait retourner à Inertia::location() un 409 + X-Inertia-Location
    // (interprété par le JS pour déclencher une navigation complète du
    // navigateur) plutôt qu'une redirection HTTP classique.
    $response = $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('/cantine/wallet/top-up', ['amount' => 20]);

    $response->assertStatus(409); // Inertia::location() : redirection externe
    $response->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/fake-session');
});

test('a student cannot start a top-up (not a parent)', function () {
    $school = makeTopUpSchool();
    $student = makeTopUpStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/wallet/top-up', ['amount' => 20])
        ->assertForbidden();
});

test('a power user cannot start a top-up (not a parent)', function () {
    $school = makeTopUpSchool();
    $powerUser = User::factory()->create();
    UserSchoolRole::create(['user_id' => $powerUser->id, 'school_id' => $school->id, 'role_id' => makeTopUpRole('POWER', 'Power User')->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/wallet/top-up', ['amount' => 20])
        ->assertForbidden();
});

test('top-up amount below the 5€ minimum is rejected', function () {
    $school = makeTopUpSchool();
    $student = makeTopUpStudent($school);
    $parent = makeTopUpParent($school, $student);

    $this->actingAs($parent)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/wallet/top-up', ['amount' => 2])
        ->assertSessionHasErrors('amount');
});
