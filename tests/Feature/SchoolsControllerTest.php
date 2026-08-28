<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeSchoolsCtrlAdmin(): User
{
    $school = School::create(['name' => 'École Admin', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'ADMIN'], ['name' => 'Administrateur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $admin = User::factory()->create();
    UserSchoolRole::create(['user_id' => $admin->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return $admin;
}

test('approving a school assigns its founder as Directeur and generates an access code', function () {
    Role::firstOrCreate(['reference' => 'DIR'], ['name' => 'Directeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $founder = User::factory()->create();
    $school = School::create([
        'name' => 'Nouvelle École', 'status' => 'P', 'is_active' => false, 'created_by' => $founder->id,
    ]);
    $admin = makeSchoolsCtrlAdmin();

    $this->actingAs($admin)
        ->withSession(['active_school_id' => UserSchoolRole::where('user_id', $admin->id)->first()->school_id])
        ->post("/schools/{$school->id}/approve")
        ->assertRedirect();

    $school->refresh();
    expect($school->status)->toBe('A')
        ->and($school->access_code)->not->toBeNull()
        ->and(strlen($school->access_code))->toBe(8);

    $directeurRole = UserSchoolRole::where('user_id', $founder->id)->where('school_id', $school->id)->first();
    expect($directeurRole)->not->toBeNull()
        ->and($directeurRole->status)->toBe('A')
        ->and($directeurRole->role->reference)->toBe('DIR');
});

test('approving a school twice does not create a duplicate Directeur role', function () {
    Role::firstOrCreate(['reference' => 'DIR'], ['name' => 'Directeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $founder = User::factory()->create();
    $school = School::create([
        'name' => 'École Bis', 'status' => 'P', 'is_active' => false, 'created_by' => $founder->id,
    ]);
    $admin = makeSchoolsCtrlAdmin();
    $activeSchoolId = UserSchoolRole::where('user_id', $admin->id)->first()->school_id;

    $this->actingAs($admin)->withSession(['active_school_id' => $activeSchoolId])
        ->post("/schools/{$school->id}/approve");

    // approve() abort_if status !== 'P' donc un second appel échoue en 422 —
    // vérifie qu'aucune ligne supplémentaire n'apparaît malgré tout.
    $this->actingAs($admin)->withSession(['active_school_id' => $activeSchoolId])
        ->post("/schools/{$school->id}/approve")
        ->assertStatus(422);

    expect(UserSchoolRole::where('user_id', $founder->id)->where('school_id', $school->id)->count())->toBe(1);
});
