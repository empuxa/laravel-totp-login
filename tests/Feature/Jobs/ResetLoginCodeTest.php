<?php

use Empuxa\TotpLogin\Jobs\ResetLoginCode;

it('can reset the code', function () {
    $user = createUser([
        config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
    ]);

    $userUpdatedAt = $user->updated_at;

    expect($user->{config('totp-login.columns.code_valid_until')}->isFuture())->toBeTrue();

    ResetLoginCode::dispatchSync($user);

    $user->fresh();

    expect($user->{config('totp-login.columns.code_valid_until')}->isFuture())->toBeFalse();

    // Timestamps have not been updated
    expect($user->updated_at)->toEqual($userUpdatedAt);
});
