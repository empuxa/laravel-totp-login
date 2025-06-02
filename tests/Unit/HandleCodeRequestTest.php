<?php

namespace Empuxa\TotpLogin\Tests\Unit;

use Empuxa\TotpLogin\Requests\CodeRequest;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;

class HandleCodeRequestTest extends TestCase
{
    public function test_runs_on_allowed_environment(): void
    {
        $this->assertFalse(CodeRequest::runsOnAllowedEnvironment(''));
        $this->assertFalse(CodeRequest::runsOnAllowedEnvironment());

        Config::set('totp-login.superpin.pin', 333333);
        Config::set('totp-login.superpin.environments', ['production']);

        $data = [
            'production' => false,
            'prod*'      => false,
            'staging'    => false,
            'testing'    => false,
            'local'      => false,
        ];

        foreach ($data as $environment => $expected) {
            $this->assertSame($expected, CodeRequest::runsOnAllowedEnvironment($environment), $environment);
        }

        Config::set('totp-login.superpin.environments', ['staging']);

        $this->assertTrue(CodeRequest::runsOnAllowedEnvironment('staging'));
    }

    public function test_bypasses_restrictions(): void
    {
        $this->assertFalse(CodeRequest::bypassesRestrictions(''));
        $this->assertFalse(CodeRequest::bypassesRestrictions());

        Config::set('totp-login.superpin.pin', 333333);
        Config::set('totp-login.superpin.environments', ['non-existing']);
        Config::set('totp-login.superpin.bypassing_identifiers', ['test@example.com']);

        $data = [
            'test@example.com'  => true,
            'test@*'            => false,
            'test2@example.com' => false,
        ];

        foreach ($data as $email => $expected) {
            $this->assertSame($expected, CodeRequest::bypassesRestrictions($email), $email);
        }
    }
}
