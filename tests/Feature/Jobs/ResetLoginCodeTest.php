<?php

namespace Empuxa\TotpLogin\Tests\Feature\Jobs;

use Empuxa\TotpLogin\Jobs\ResetLoginCode;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;

class ResetLoginCodeTest extends TestbenchTestCase
{
    public function test_can_reset_the_pin(): void
    {
        $user = $this->createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $userUpdatedAt = $user->updated_at;

        $this->assertTrue($user->{config('totp-login.columns.code_valid_until')}->isFuture());

        ResetLoginCode::dispatchSync($user);

        $user->fresh();

        $this->assertFalse($user->{config('totp-login.columns.code_valid_until')}->isFuture());

        // Timestamps have not been updated
        $this->assertEquals($userUpdatedAt, $user->updated_at);
    }
}
