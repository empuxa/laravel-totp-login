<?php

use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Empuxa\TotpLogin\Notifications\LoginCode;
use Empuxa\TotpLogin\Requests\IdentifierRequest;
use Illuminate\Support\Facades\Hash;
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

it('expires an undelivered replacement so automatic retry can send a usable code', function () {
    $user = createUser(['login_totp_code_valid_until' => now()->subMinute()]);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('mail unavailable'));
    expect(fn () => CreateAndSendLoginCode::dispatchSync($user, '', true))
        ->toThrow(RuntimeException::class, 'mail unavailable');
    expect(now()->greaterThan($user->fresh()->login_totp_code_valid_until))->toBeTrue();

    Notification::fake();
    CreateAndSendLoginCode::dispatchSync($user, '', true);
    $notification = Notification::sent($user, LoginCode::class)->sole();
    $code = implode('', $notification->toMail($user->fresh())->viewData['code']);
    expect(Hash::check($code, $user->fresh()->login_totp_code))->toBeTrue();
    $this->withSession(['email' => $user->email])->post(route('totp-login.code.handle'), [
        'code' => str_split($code),
    ])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

it('preserves a newer code when an earlier notification fails', function () {
    $user = createUser();
    $newHash = Hash::make('654321');
    $newExpiry = now()->addMinutes(15)->startOfSecond();
    Notification::shouldReceive('send')->once()->andReturnUsing(function () use ($user, $newHash, $newExpiry) {
        // Simulate another writer completing before the first send reports failure.
        $user->newQuery()->whereKey($user->getKey())->update([
            'login_totp_code'             => $newHash,
            'login_totp_code_valid_until' => $newExpiry,
        ]);
        throw new RuntimeException('earlier send failed');
    });
    expect(fn () => CreateAndSendLoginCode::dispatchSync($user))->toThrow(RuntimeException::class, 'earlier send failed');
    expect($user->fresh()->login_totp_code)->toBe($newHash);
    expect((string) $user->fresh()->login_totp_code_valid_until)->toBe($newExpiry->toDateTimeString());
});
