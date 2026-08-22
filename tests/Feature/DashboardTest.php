<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    // `dashboard` est derrière le middleware `school.context` : un utilisateur
    // sans école active est redirigé vers l'onboarding, pas un 200 direct.
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'ELEVE'], [
        'name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $user = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});