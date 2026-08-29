<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;

function createSchoolWithRole(string $roleReference, string $roleName): array
{
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => $roleReference], [
        'name' => $roleName, 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $user = User::factory()->create();
    UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return [$school, $user];
}

test('a director can access their school panel', function () {
    [$school, $user] = createSchoolWithRole('DIR', 'Directeur');

    $this->actingAs($user)->get(route('school.panel', $school))->assertOk();
});

test('the platform Administrateur cannot access a school panel even with a role row scoped to that school', function () {
    // L'Administrateur possède parfois une ligne UserSchoolRole rattachée à une
    // school_id précise (ex : compte de démo), mais ce n'est pas une école
    // qu'il gère — pas d'autorité sur le contenu académique (CLAUDE.md).
    [$school, $user] = createSchoolWithRole('ADMIN', 'Administrateur');

    $this->actingAs($user)->get(route('school.panel', $school))->assertNotFound();
});
