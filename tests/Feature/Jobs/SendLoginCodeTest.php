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
