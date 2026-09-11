<?php

use Empuxa\TotpLogin\Models\User;
use Empuxa\TotpLogin\TotpLoginServiceProvider;
use Illuminate\Support\ServiceProvider;

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

it('registers publishable local assets for both login forms', function () {
    $paths = ServiceProvider::pathsToPublish(
        TotpLoginServiceProvider::class,
        'totp-login-assets',
    );
    expect($paths)->not->toBeEmpty();
    foreach ($paths as $source => $destination) {
        expect(is_file($source . '/login.css'))->toBeTrue();
        expect(is_file($source . '/login.js'))->toBeTrue();
        expect($destination)->toBe(public_path('vendor/totp-login'));
    }
    $html = $this->get(route('totp-login.identifier.form'))->getContent();
    expect($html)->toContain('/vendor/totp-login/login.css')->not->toContain('cdn.');
    $html = $this->withSession(['email' => 'test@example.com'])->get(route('totp-login.code.form'))->getContent();
    expect($html)->toContain('/vendor/totp-login/login.js')->not->toContain('cdn.');
});
