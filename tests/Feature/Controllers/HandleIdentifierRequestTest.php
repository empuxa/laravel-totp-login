<?php

use Empuxa\TotpLogin\Notifications\LoginCode;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(TestbenchTestCase::class);

it('sends email to user', function () {
    Notification::fake();

    $user = createUser();

    $response = $this->post(route('totp-login.identifier.handle'), [
        config('totp-login.columns.identifier') => $user->email,
    ]);

    $response->assertSessionHasNoErrors();

    $response->assertRedirect(route('totp-login.code.form'));

    expect(Auth::check())->toBeFalse();

    Notification::assertSentTo($user, LoginCode::class);
});

it('does not send email to user with wrong email', function () {
    Notification::fake();

    $response = $this->post(route('totp-login.identifier.handle'), [
        config('totp-login.columns.identifier') => 'not_existing@example.com',
    ]);

    $response->assertSessionHasErrors('email', __('auth.failed'));

    expect(Auth::check())->toBeFalse();

    Notification::assertNothingSent();
});

it('does not send email to user with rate limit', function () {
    Config::set('totp-login.identifier.enable_throttling', true);

    Event::fake();
    Notification::fake();

    for ($i = 0; $i < config('totp-login.identifier.max_attempts'); $i++) {
        $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => 'non_existing@example.com',
        ]);
    }

    $event = config('totp-login.events.lockout');
    Event::assertDispatched($event);

    expect(Auth::check())->toBeFalse();

    Notification::assertNothingSent();
});

it('sends email to user through disabled rate limit', function () {
    Config::set('totp-login.identifier.enable_throttling', false);

    Notification::fake();

    $user = createUser();

    for ($i = 0; $i < config('totp-login.identifier.max_attempts'); $i++) {
        $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => 'non_existing@example.com',
        ]);
    }

    $response = $this->post(route('totp-login.identifier.handle'), [
        config('totp-login.columns.identifier') => $user->email,
    ]);

    $response->assertSessionHasNoErrors();

    $response->assertRedirect(route('totp-login.code.form'));

    expect(Auth::check())->toBeFalse();

    Notification::assertSentTo($user, LoginCode::class);
});
