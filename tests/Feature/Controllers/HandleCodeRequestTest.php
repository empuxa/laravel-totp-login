<?php

use Empuxa\TotpLogin\Models\User;
use Empuxa\TotpLogin\Notifications\LoginCode;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(TestbenchTestCase::class);

// Helper function for creating a user in tests
function createUser(array $attributes = []): User
{
    return test()->createUser($attributes);
}

it('cannot login with wrong code', function () {
    Notification::fake();

    $user = createUser();

    $response = $this
        ->withSession([
            config('totp-login.columns.identifier')       => $user->{config('totp-login.columns.identifier')},
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ])
        ->post(route('totp-login.code.handle'), [
            'code' => [9, 9, 9, 9, 9, 9],
        ]);

    $response->assertStatus(302);

    $response->assertSessionHasErrors('code', __('controllers/session.store.error.totp_wrong', [
        'attempts_left' => config('totp-login.code.max_attempts') - 1,
    ]));

    expect(Auth::check())->toBeFalse();

    Notification::assertNothingSent();
});

it('cannot login with expired session', function () {
    Notification::fake();

    $user = createUser([
        config('totp-login.columns.code_valid_until') => now()->subSecond(),
    ]);

    $response = $this
        ->withSession([
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ])
        ->post(route('totp-login.code.handle'), [
            'code' => [1, 2, 3, 4, 5, 6],
        ]);

    $response->assertStatus(302);

    $response->assertSessionHasErrors('code', __('controllers/session.store.error.expired'));

    expect(Auth::check())->toBeFalse();

    Notification::assertSentTo($user, LoginCode::class);
});

it('cannot login with rate limit', function () {
    Notification::fake();

    Config::set('totp-login.code.enable_throttling', true);

    $user = createUser([
        config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
    ]);

    $session = [
        config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
    ];

    for ($i = 0; $i < config('totp-login.code.max_attempts'); $i++) {
        $this
            ->withSession($session)
            ->post(route('totp-login.code.handle'), [
                'code' => [9, 9, 9, 9, 9, 9],
            ]);
    }

    $response = $this
        ->withSession($session)
        ->post(route('totp-login.code.handle'), [
            'code' => [1, 2, 3, 4, 5, 6],
        ]);

    $response->assertStatus(302);

    $response->assertSessionHasErrors('code', __('controllers/session.store.error.rate_limit', [
        'seconds' => RateLimiter::availableIn($user->{config('totp-login.columns.identifier')}),
    ]));

    expect(Auth::check())->toBeFalse();

    Notification::assertNothingSent();
});

function loginWithCode(array $code): void
{
    $formattedCode = '';

    collect($code)->each(function ($digit) use (&$formattedCode): void {
        $formattedCode .= $digit;
    });

    $user = createUser([
        config('totp-login.columns.identifier')       => $formattedCode . '@example.com',
        config('totp-login.columns.code')             => Hash::make($formattedCode),
        config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
    ]);

    $response = test()
        ->withSession([
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ])
        ->post(route('totp-login.code.handle'), [
            'code' => $code,
        ]);

    $response->assertStatus(302);

    $response->assertSessionHasNoErrors();

    $response->assertRedirect(config('totp-login.redirect'));

    expect(Auth::user())->toBe($user);
}

it('can login with correct code', function () {
    Notification::fake();

    $codes = [
        [1, 2, 3, 4, 5, 6],
        [9, 8, 7, 6, 5, 4],
    ];

    foreach ($codes as $code) {
        loginWithCode($code);
    }
});

it('can login through disabled rate limit', function () {
    Notification::fake();

    Config::set('totp-login.code.enable_throttling', false);

    $user = createUser([
        config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
    ]);

    $session = [
        config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
    ];

    for ($i = 0; $i < config('totp-login.code.max_attempts'); $i++) {
        $this
            ->withSession($session)
            ->post(route('totp-login.code.handle'), [
                'code' => [9, 9, 9, 9, 9, 9],
            ]);
    }

    $response = $this
        ->withSession($session)
        ->post(route('totp-login.code.handle'), [
            'code' => [1, 2, 3, 4, 5, 6],
        ]);

    $response->assertStatus(302);

    $response->assertSessionHasNoErrors();

    $response->assertRedirect(config('totp-login.redirect'));

    expect(Auth::user())->toBe($user);

    Notification::assertNothingSent();
});

it('can login with superpin', function () {
    Notification::fake();

    Config::set('totp-login.superpin.pin', 333333);

    $user = createUser([
        config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
    ]);

    $response = $this
        ->withSession([
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ])
        ->post(route('totp-login.code.handle'), [
            'code' => [3, 3, 3, 3, 3, 3],
        ]);

    $response->assertSessionHasNoErrors();

    $response->assertRedirect(config('totp-login.redirect'));

    expect(Auth::user())->toBe($user);

    Notification::assertNothingSent();
});

it('cannot login with superpin on wrong environment', function () {
    Notification::fake();

    Config::set('totp-login.superpin.pin', 333333);
    Config::set('totp-login.superpin.environments', ['production']);

    $user = createUser([
        config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
    ]);

    $response = $this
        ->withSession([
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ])
        ->post(route('totp-login.code.handle'), [
            'code' => [3, 3, 3, 3, 3, 3],
        ]);

    $response->assertRedirect();

    $response->assertSessionHasErrors('code', __('controllers/session.store.error.totp_wrong', [
        'attempts_left' => config('totp-login.code.max_attempts') - 1,
    ]));

    expect(Auth::check())->toBeFalse();

    Notification::assertNothingSent();
});

it('can login with superpin on wrong environment with bypassing identifier', function () {
    Notification::fake();

    Config::set('totp-login.superpin.pin', 333333);
    Config::set('totp-login.superpin.environments', ['production']);
    Config::set('totp-login.superpin.bypassing_identifiers', ['test@example.com']);

    $user = createUser([
        config('totp-login.columns.identifier')       => 'test@example.com',
        config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
    ]);

    $response = $this
        ->withSession([
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ])
        ->post(route('totp-login.code.handle'), [
            'code' => [3, 3, 3, 3, 3, 3],
        ]);

    $response->assertSessionHasNoErrors();

    $response->assertRedirect(config('totp-login.redirect'));

    expect(Auth::user())->toBe($user);

    Notification::assertNothingSent();
});
