<?php

use Empuxa\TotpLogin\Requests\CodeRequest;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Support\Facades\Config;

uses(TestbenchTestCase::class);

it('runs on allowed environment', function () {
    expect(CodeRequest::runsOnAllowedEnvironment(''))->toBeFalse();
    expect(CodeRequest::runsOnAllowedEnvironment())->toBeFalse();

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
        expect(CodeRequest::runsOnAllowedEnvironment($environment))->toBe($expected);
    }

    Config::set('totp-login.superpin.environments', ['staging']);

    expect(CodeRequest::runsOnAllowedEnvironment('staging'))->toBeTrue();
});

it('bypasses restrictions', function () {
    expect(CodeRequest::bypassesRestrictions(''))->toBeFalse();
    expect(CodeRequest::bypassesRestrictions())->toBeFalse();

    Config::set('totp-login.superpin.pin', 333333);
    Config::set('totp-login.superpin.environments', ['non-existing']);
    Config::set('totp-login.superpin.bypassing_identifiers', ['test@example.com']);

    $data = [
        'test@example.com'  => true,
        'test@*'            => false,
        'test2@example.com' => false,
    ];

    foreach ($data as $email => $expected) {
        expect(CodeRequest::bypassesRestrictions($email))->toBe($expected);
    }
});
