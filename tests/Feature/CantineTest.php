<?php

use App\Models\CantinePresence;
use App\Models\CantineRegistration;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeCantineSchool(bool $enabled = true): School
{
    return School::create([
        'name' => 'École', 'status' => 'A', 'is_active' => true,
        'cantine_enabled' => $enabled, 'created_by' => 1,
    ]);
}

function makeCantineRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], [
        'name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeCantineUsr(School $school, Role $role): UserSchoolRole
{
    $user = User::factory()->create();

    return UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

function makeCantineStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $studentUsr = makeCantineUsr($school, makeCantineRole('ELEVE', 'Élève'));

    return SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $studentUsr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('the cantine module 404s when disabled for the active school', function () {
    $school = makeCantineSchool(enabled: false);
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine')
        ->assertNotFound();
});

test('a power user can register a student for cantine on a given day', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $student = makeCantineStudent($school);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine', ['section_user_id' => $student->id, 'day_of_week' => 1])
        ->assertRedirect('/cantine');

    expect(CantineRegistration::where('section_user_id', $student->id)->where('day_of_week', 1)->exists())->toBeTrue();
});

test('a teacher can register a student for cantine', function () {
    $school = makeCantineSchool();
    $teacher = makeCantineUsr($school, makeCantineRole('PROF', 'Professeur'))->user;
    $student = makeCantineStudent($school);

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine', ['section_user_id' => $student->id, 'day_of_week' => 1])
        ->assertRedirect('/cantine');

    expect(CantineRegistration::where('section_user_id', $student->id)->where('day_of_week', 1)->exists())->toBeTrue();
});

test('an administrateur cannot register a student for cantine', function () {
    $school = makeCantineSchool();
    $admin = makeCantineUsr($school, makeCantineRole('ADMIN', 'Administrateur'))->user;
    $student = makeCantineStudent($school);

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine', ['section_user_id' => $student->id, 'day_of_week' => 1])
        ->assertForbidden();
});

test('registering a student from another school is rejected', function () {
    $schoolA = makeCantineSchool();
    $schoolB = makeCantineSchool();
    $powerUserA = makeCantineUsr($schoolA, makeCantineRole('POWER', 'Power User'))->user;
    $studentB = makeCantineStudent($schoolB);

    $this->actingAs($powerUserA)
        ->withSession(['active_school_id' => $schoolA->id])
        ->post('/cantine', ['section_user_id' => $studentB->id, 'day_of_week' => 1])
        ->assertSessionHasErrors('section_user_id');
});

test('a power user can view the roster for a given day and mark presences', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $student = makeCantineStudent($school);

    // 2026-08-24 est un lundi (day_of_week ISO = 1)
    $registration = CantineRegistration::create([
        'school_id' => $school->id, 'section_user_id' => $student->id, 'day_of_week' => 1,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine/roster?date=2026-08-24')
        ->assertInertia(fn ($page) => $page
            ->component('power-user/web/Cantine/Roster')
            ->has('roster', 1)
            ->where('roster.0.cantine_registration_id', $registration->id)
        );

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine/roster', [
            'date' => '2026-08-24',
            'presences' => [
                ['cantine_registration_id' => $registration->id, 'is_present' => false, 'note' => 'Absent, malade'],
            ],
        ])
        ->assertRedirect();

    $presence = CantinePresence::where('cantine_registration_id', $registration->id)->where('date', '2026-08-24')->first();
    expect($presence)->not->toBeNull();
    expect($presence->is_present)->toBeFalse();
    expect($presence->note)->toBe('Absent, malade');
});

test('the roster only includes students registered for the requested day of week', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $mondayStudent = makeCantineStudent($school);
    $tuesdayStudent = makeCantineStudent($school);

    CantineRegistration::create([
        'school_id' => $school->id, 'section_user_id' => $mondayStudent->id, 'day_of_week' => 1,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    CantineRegistration::create([
        'school_id' => $school->id, 'section_user_id' => $tuesdayStudent->id, 'day_of_week' => 2,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    // 2026-08-24 = lundi : seul mondayStudent doit apparaître
    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine/roster?date=2026-08-24')
        ->assertInertia(fn ($page) => $page->has('roster', 1));
});

test('a power user cannot register the same student twice for the same day', function () {
    $school = makeCantineSchool();
    $powerUser = makeCantineUsr($school, makeCantineRole('POWER', 'Power User'))->user;
    $student = makeCantineStudent($school);

    CantineRegistration::create([
        'school_id' => $school->id, 'section_user_id' => $student->id, 'day_of_week' => 1,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post('/cantine', ['section_user_id' => $student->id, 'day_of_week' => 1])
        ->assertSessionHasErrors();
});
