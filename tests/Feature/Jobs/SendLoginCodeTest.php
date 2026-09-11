<?php

use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Illuminate\Support\Facades\Notification;

it('can send notification', function () {
    Notification::fake();

    $user = createUser([
        config('totp-login.columns.code_valid_until') => now(),
    ]);

    expect($user->{config('totp-login.columns.code_valid_until')}->isFuture())->toBeFalse();

    $userLoginCode = $user->{config('totp-login.columns.code')};
    $userUpdatedAt = $user->updated_at;

    CreateAndSendLoginCode::dispatchSync($user);

    $user->fresh();

    expect($user->{config('totp-login.columns.code_valid_until')}->isFuture())->toBeTrue();

    // @todo fix this assignment
    // expect($user->updated_at)->toEqual($userUpdatedAt);

    expect($user->{config('totp-login.columns.code')})->not->toBe($userLoginCode);
});

it('persists the replacement code sent after expiry and accepts it', function () {
    Notification::fake();
    $user = createUser(['login_totp_code_valid_until' => now()->subMinute()]);
    $oldHash = $user->login_totp_code;
    $this->withSession(['email' => $user->email])->post(route('totp-login.code.handle'), [
        'code' => str_split('123456'),
    ])->assertSessionHasErrors('code');

    $notification = Notification::sent($user, \Empuxa\TotpLogin\Notifications\LoginCode::class)->sole();
    $code = implode('', $notification->toMail($user->fresh())->viewData['code']);
    expect($user->fresh()->login_totp_code)->not->toBe($oldHash);
    expect(\Illuminate\Support\Facades\Hash::check($code, $user->fresh()->login_totp_code))->toBeTrue();
    expect(now()->lessThan($user->fresh()->login_totp_code_valid_until))->toBeTrue();
    $this->post(route('totp-login.code.handle'), ['code' => str_split($code)])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

it('does not send a code when the outer transaction rolls back', function () {
    Notification::fake();
    $user = createUser();
    $hash = $user->login_totp_code;
    try {
        $user->getConnection()->transaction(function () use ($user) {
            CreateAndSendLoginCode::dispatchSync($user);
            Notification::assertNothingSent();
            throw new \RuntimeException('rollback');
        });
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback');
    }
    Notification::assertNothingSent();
    expect($user->fresh()->login_totp_code)->toBe($hash);
});

it('does not replace a code that was already renewed', function () {
    Notification::fake();
    $user = createUser();
    $hash = $user->login_totp_code;
    CreateAndSendLoginCode::dispatchSync($user, '', true);
    Notification::assertNothingSent();
    expect($user->fresh()->login_totp_code)->toBe($hash);
});
