<?php

use App\Models\School;
use App\Models\SchoolInvitation;
use App\Models\User;

test('submitting the wizard saves the cantine module fields on the school', function () {
    $founder = User::factory()->create();

    $this->actingAs($founder)->post('/school/create', [
        'name' => 'École Cantine',
        'cantine_enabled' => true,
        'cantine_meal_price' => '3.50',
    ])->assertRedirect();

    $school = School::where('name', 'École Cantine')->firstOrFail();
    expect($school->cantine_enabled)->toBeTrue()
        ->and($school->cantine_meal_price)->toBe(3.5);
});

test('cantine_meal_price is required when cantine_enabled is true', function () {
    $founder = User::factory()->create();

    $this->actingAs($founder)->post('/school/create', [
        'name' => 'École Sans Prix',
        'cantine_enabled' => true,
    ])->assertSessionHasErrors('cantine_meal_price');

    expect(School::where('name', 'École Sans Prix')->exists())->toBeFalse();
});

test('cantine_meal_price is not required when cantine_enabled is false', function () {
    $founder = User::factory()->create();

    $this->actingAs($founder)->post('/school/create', [
        'name' => 'École Sans Cantine',
        'cantine_enabled' => false,
    ])->assertRedirect();

    expect(School::where('name', 'École Sans Cantine')->exists())->toBeTrue();
});

test('submitting invites during creation stores them as drafts, without creating real invitations', function () {
    $founder = User::factory()->create();

    $this->actingAs($founder)->post('/school/create', [
        'name' => 'École Invites',
        'invites' => [
            ['email' => 'prof@example.com', 'role_reference' => 'PROF'],
            ['email' => 'codir@example.com', 'role_reference' => 'DIR'],
        ],
    ])->assertRedirect();

    $school = School::where('name', 'École Invites')->firstOrFail();
    expect($school->pending_invites)->toBe([
        ['email' => 'prof@example.com', 'role_reference' => 'PROF'],
        ['email' => 'codir@example.com', 'role_reference' => 'DIR'],
    ]);
    expect(SchoolInvitation::count())->toBe(0);
});

test('an invite role outside the creation allowlist is rejected', function () {
    $founder = User::factory()->create();

    $this->actingAs($founder)->post('/school/create', [
        'name' => 'École Rôle Invalide',
        'invites' => [
            ['email' => 'eleve@example.com', 'role_reference' => 'ELEVE'],
        ],
    ])->assertSessionHasErrors('invites.0.role_reference');

    expect(School::where('name', 'École Rôle Invalide')->exists())->toBeFalse();
});

test('submitting the wizard with no invites leaves pending_invites null', function () {
    $founder = User::factory()->create();

    $this->actingAs($founder)->post('/school/create', [
        'name' => 'École Sans Invites',
    ])->assertRedirect();

    $school = School::where('name', 'École Sans Invites')->firstOrFail();
    expect($school->pending_invites)->toBeNull();
});
