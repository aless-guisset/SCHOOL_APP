<?php

test('the homepage exposes all 3 entry points for an anonymous visitor', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->where('canRegister', true)
    );
});

test('creer un etablissement leads to the founder registration form', function () {
    $this->get('/register')->assertOk();
});

test('creer un compte leads to the role selection page', function () {
    $this->get('/join/role')->assertOk();
});

test('se connecter still leads to the login page', function () {
    $this->get('/login')->assertOk();
});
