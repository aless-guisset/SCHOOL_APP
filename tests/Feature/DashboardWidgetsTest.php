<?php

use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;

test('user school role exposes its section_users rows via sectionUserRoles', function () {
    $school = School::create([
        'name' => 'Lycée Test', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $role = Role::firstOrCreate(['reference' => 'PROF'], [
        'name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $user = User::factory()->create();
    $usr = UserSchoolRole::create([
        'user_id' => $user->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $section = Section::create([
        'school_id' => $school->id, 'name' => 'Classe A',
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
    $sectionUser = SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $usr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    expect($usr->sectionUserRoles)->toHaveCount(1);
    expect($usr->sectionUserRoles->first()->id)->toBe($sectionUser->id);
});
