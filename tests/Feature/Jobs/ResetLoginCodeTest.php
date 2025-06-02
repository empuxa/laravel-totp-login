<?php

use Empuxa\TotpLogin\Jobs\ResetLoginCode;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;

uses(TestbenchTestCase::class);

it('can reset the pin', function () {
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
