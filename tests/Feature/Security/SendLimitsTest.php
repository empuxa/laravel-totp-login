<?php

use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Empuxa\TotpLogin\Notifications\LoginCode;
use Empuxa\TotpLogin\Requests\IdentifierRequest;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

it('counts successful requests and preserves the code during cooldown', function () {
    Notification::fake();
    $user = createUser();
    $this->post(route('totp-login.identifier.handle'), ['email' => $user->email])->assertSessionHasNoErrors();
    $hash = $user->fresh()->login_totp_code;
    $this->post(route('totp-login.identifier.handle'), ['email' => $user->email])->assertSessionHasNoErrors();
    Notification::assertSentToTimes($user, LoginCode::class, 1);
    expect($user->fresh()->login_totp_code)->toBe($hash);
    $request = IdentifierRequest::create('/', 'POST', ['email' => $user->email]);
    expect(RateLimiter::attempts($request->throttleKey()))->toBe(2);
    $this->travel(31)->seconds();
    $this->post(route('totp-login.identifier.handle'), ['email' => $user->email])->assertSessionHasNoErrors();
    Notification::assertSentToTimes($user, LoginCode::class, 2);
});

it('limits requests from one IP across different accounts', function () {
    Notification::fake();
    config(['totp-login.identifier.max_attempts_per_ip' => 1]);
    $user = createUser();
    $this->post(route('totp-login.identifier.handle'), ['email' => 'unknown@example.com']);
    $this->post(route('totp-login.identifier.handle'), ['email' => $user->email])->assertSessionHasNoErrors();
    Notification::assertNothingSent();
});

it('releases the send reservation after notification failure', function () {
    $user = createUser();
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('mail unavailable'));
    expect(fn () => CreateAndSendLoginCode::dispatchSync($user))->toThrow(RuntimeException::class);
    Notification::fake();
    CreateAndSendLoginCode::dispatchSync($user);
    Notification::assertSentToTimes($user, LoginCode::class, 1);
});

it('shares cooldown between manual and automatic sends', function () {
    Notification::fake();
    $user = createUser();
    CreateAndSendLoginCode::dispatchSync($user);
    $user->login_totp_code_valid_until = now()->subMinute();
    $user->saveQuietly();
    CreateAndSendLoginCode::dispatchSync($user, '', true);
    Notification::assertSentToTimes($user, LoginCode::class, 1);
});
