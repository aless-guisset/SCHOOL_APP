<?php

use App\Models\CantineTransaction;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeStaffWalletSchool(): School
{
    return School::create(['name' => 'École Staff Solde', 'status' => 'A', 'is_active' => true, 'cantine_enabled' => true, 'created_by' => 1]);
}

function makeStaffWalletRole(string $reference, string $name): Role
{
    return Role::firstOrCreate(['reference' => $reference], ['name' => $name, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeStaffWalletUsr(School $school, Role $role): UserSchoolRole
{
    return UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function makeStaffWalletStudent(School $school): SectionUserSchoolRole
{
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $usr = makeStaffWalletUsr($school, makeStaffWalletRole('ELEVE', 'Élève'));

    return SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

test('a power user can manually credit a student balance', function () {
    $school = makeStaffWalletSchool();
    $powerUser = makeStaffWalletUsr($school, makeStaffWalletRole('POWER', 'Power User'))->user;
    $student = makeStaffWalletStudent($school);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->post("/cantine/wallet/{$student->id}/manual-credit", ['amount' => 15, 'note' => 'Espèces'])
        ->assertRedirect();

    expect($student->cantineBalance())->toBe(15.0);
});

test('a directeur cannot manually credit a student balance', function () {
    $school = makeStaffWalletSchool();
    $directeur = makeStaffWalletUsr($school, makeStaffWalletRole('DIR', 'Directeur'))->user;
    $student = makeStaffWalletStudent($school);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->post("/cantine/wallet/{$student->id}/manual-credit", ['amount' => 15])
        ->assertForbidden();
});

test('a directeur can view the wallets index and a student wallet (read-only)', function () {
    $school = makeStaffWalletSchool();
    $directeur = makeStaffWalletUsr($school, makeStaffWalletRole('DIR', 'Directeur'))->user;
    $student = makeStaffWalletStudent($school);

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine/wallet')
        ->assertOk();

    $this->actingAs($directeur)
        ->withSession(['active_school_id' => $school->id])
        ->get("/cantine/wallet/{$student->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can_write', false));
});

test('an élève cannot view the wallets index', function () {
    $school = makeStaffWalletSchool();
    $student = makeStaffWalletStudent($school);
    $studentUser = User::find($student->userschoolrole->user_id);

    $this->actingAs($studentUser)
        ->withSession(['active_school_id' => $school->id])
        ->get('/cantine/wallet')
        ->assertForbidden();
});

test('a power user can void a manual credit', function () {
    $school = makeStaffWalletSchool();
    $powerUser = makeStaffWalletUsr($school, makeStaffWalletRole('POWER', 'Power User'))->user;
    $student = makeStaffWalletStudent($school);
    $credit = CantineTransaction::create(['section_user_id' => $student->id, 'type' => 'manual_credit', 'amount' => 15, 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/cantine/wallet/manual-credit/{$credit->id}")
        ->assertRedirect();

    expect($student->cantineBalance())->toBe(0.0);
});

test('voiding a stripe_topup transaction is rejected (manual credits only)', function () {
    $school = makeStaffWalletSchool();
    $powerUser = makeStaffWalletUsr($school, makeStaffWalletRole('POWER', 'Power User'))->user;
    $student = makeStaffWalletStudent($school);
    $topup = CantineTransaction::create(['section_user_id' => $student->id, 'type' => 'stripe_topup', 'amount' => 15, 'stripe_payment_intent_id' => 'pi_x', 'is_active' => true, 'created_by' => 1]);

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->delete("/cantine/wallet/manual-credit/{$topup->id}")
        ->assertStatus(422);

    expect($student->cantineBalance())->toBe(15.0);
});

test('a power user can set the meal price', function () {
    $school = makeStaffWalletSchool();
    $powerUser = makeStaffWalletUsr($school, makeStaffWalletRole('POWER', 'Power User'))->user;

    $this->actingAs($powerUser)
        ->withSession(['active_school_id' => $school->id])
        ->put('/cantine/price', ['cantine_meal_price' => 4.5])
        ->assertRedirect();

    expect($school->refresh()->cantine_meal_price)->toBe(4.5);
});
