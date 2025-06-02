<?php

use Empuxa\TotpLogin\Models\User;

it('cannot render code screen because of missing session', function () {
    $response = $this->get(route('totp-login.code.form'));

    $response->assertServerError();
});

it('can render code screen', function () {
    $response = $this
        ->withSession([
            config('totp-login.columns.identifier') => 'admin@example.com',
        ])
        ->get(route('totp-login.code.form'));

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

    $response = $this->get(route('totp-login.code.form'));

    $response->assertRedirect();
});
