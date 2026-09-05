<?php

use App\Models\CantineTransaction;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeWalletSchool(): School
{
    return School::create(['name' => 'École Solde', 'status' => 'A', 'is_active' => true, 'cantine_enabled' => true, 'created_by' => 1]);
}

function makeWalletStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $usr = UserSchoolRole::create([
        'user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);

    return SectionUserSchoolRole::create([
        'section_id' => $section->id, 'user_school_role_id' => $usr->id,
        'status' => 'A', 'is_active' => true, 'created_by' => 1,
    ]);
}

test('cantineBalance sums active transactions and excludes soft-deleted ones', function () {
    $school = makeWalletSchool();
    $student = makeWalletStudent($school);

    CantineTransaction::create(['section_user_id' => $student->id, 'type' => 'stripe_topup', 'amount' => 20, 'is_active' => true, 'created_by' => 1]);
    CantineTransaction::create(['section_user_id' => $student->id, 'type' => 'order_debit', 'amount' => -4.5, 'is_active' => true, 'created_by' => 1]);
    $voided = CantineTransaction::create(['section_user_id' => $student->id, 'type' => 'manual_credit', 'amount' => 100, 'is_active' => true, 'created_by' => 1]);
    $voided->update(['is_active' => false]);
    $voided->delete();

    expect($student->cantineBalance())->toBe(15.5);
});

test('cantineBalance is zero for a student with no transactions', function () {
    $school = makeWalletSchool();
    $student = makeWalletStudent($school);

    expect($student->cantineBalance())->toBe(0.0);
});
