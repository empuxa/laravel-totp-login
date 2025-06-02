<?php

use Empuxa\TotpLogin\Models\User;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;

uses(TestbenchTestCase::class);

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
