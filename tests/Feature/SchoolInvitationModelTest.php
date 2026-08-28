<?php

use App\Models\Role;
use App\Models\School;
use App\Models\SchoolInvitation;

function makeInvitationSchool(): School
{
    return School::create(['name' => 'École Invitation', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('isValid is true for a fresh unaccepted invitation, false once accepted or expired', function () {
    $school = makeInvitationSchool();
    $role = Role::firstOrCreate(['reference' => 'PROF'], ['name' => 'Professeur', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    $fresh = SchoolInvitation::create([
        'school_id' => $school->id, 'email' => 'a@example.com', 'role_id' => $role->id,
        'token' => 'tok1', 'expires_at' => now()->addDays(7), 'is_active' => true, 'created_by' => 1,
    ]);
    expect($fresh->isValid())->toBeTrue();

    $accepted = SchoolInvitation::create([
        'school_id' => $school->id, 'email' => 'b@example.com', 'role_id' => $role->id,
        'token' => 'tok2', 'expires_at' => now()->addDays(7), 'accepted_at' => now(), 'is_active' => true, 'created_by' => 1,
    ]);
    expect($accepted->isValid())->toBeFalse();

    $expired = SchoolInvitation::create([
        'school_id' => $school->id, 'email' => 'c@example.com', 'role_id' => $role->id,
        'token' => 'tok3', 'expires_at' => now()->subDay(), 'is_active' => true, 'created_by' => 1,
    ]);
    expect($expired->isValid())->toBeFalse();
});
