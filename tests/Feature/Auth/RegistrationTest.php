<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'firstname' => 'Test',
        'lastname'  => 'User',
        'email'     => 'test@example.com',
        'password'  => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    expect(auth()->user()->profile)->toBe('school_owner');
    $response->assertRedirect(route('school.create', absolute: false));
});

test('the profile field cannot be set by the client — always forced to school_owner', function () {
    $response = $this->post(route('register.store'), [
        'firstname' => 'Test', 'lastname' => 'Student', 'email' => 'student@example.com',
        'password' => 'password', 'password_confirmation' => 'password', 'profile' => 'student',
    ]);

    $this->assertAuthenticated();
    expect(auth()->user()->profile)->toBe('school_owner');
    $response->assertRedirect(route('school.create', absolute: false));
});
