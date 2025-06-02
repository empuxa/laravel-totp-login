<?php

use Empuxa\TotpLogin\Models\User;

it('can render login screen', function () {
    $response = $this->get(route('totp-login.identifier.form'));

    $response->assertOk();
});

it('redirects when already logged in', function () {
    $this->withoutMiddleware();

    $user = User::create([
        'name'     => 'Admin',
        'email'    => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('totp-login.identifier.form'));

    $response->assertRedirect();
});
