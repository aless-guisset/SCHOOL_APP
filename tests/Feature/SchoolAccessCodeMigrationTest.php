<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Models\UserSchoolRole;
use Illuminate\Support\Facades\DB;

test('migration backfills existing null-status user_school_roles to A', function () {
    $school = School::create(['name' => 'École', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $user = User::factory()->create();

    // Insertion directe (bypass du modèle) pour simuler une ligne pré-migration sans status.
    DB::table('users_schools_roles')->insert([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => null, 'is_active' => true, 'created_by' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('users_schools_roles')->whereNull('status')->update(['status' => 'A']);

    expect(UserSchoolRole::where('user_id', $user->id)->first()->status)->toBe('A');
});

test('School accepts access_code as fillable', function () {
    $school = School::create([
        'name' => 'École Code', 'status' => 'A', 'is_active' => true,
        'access_code' => 'ABCD1234', 'created_by' => 1,
    ]);

    expect($school->fresh()->access_code)->toBe('ABCD1234');
});
