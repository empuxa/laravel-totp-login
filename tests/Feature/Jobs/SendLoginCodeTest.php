<?php

namespace Empuxa\TotpLogin\Tests\Feature\Jobs;

use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Support\Facades\Notification;

class SendLoginCodeTest extends TestbenchTestCase
{
    public function test_can_send_notification(): void
    {
        Notification::fake();

        $user = $this->createUser([
            config('totp-login.columns.code_valid_until') => now(),
        ]);

        $this->assertFalse($user->{config('totp-login.columns.code_valid_until')}->isFuture());

        $userLoginPin = $user->{config('totp-login.columns.code')};
        $userUpdatedAt = $user->updated_at;

        CreateAndSendLoginCode::dispatchSync($user);

        $user->fresh();

        $this->assertTrue($user->{config('totp-login.columns.code_valid_until')}->isFuture());

        // @todo fix this assignment
        // $this->assertEquals($userUpdatedAt, $user->updated_at);

        $this->assertNotSame($userLoginPin, $user->{config('totp-login.columns.code')});
    }
}
