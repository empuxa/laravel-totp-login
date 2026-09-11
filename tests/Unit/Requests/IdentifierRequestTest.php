<?php

use Empuxa\TotpLogin\Requests\IdentifierRequest;

it('uses an opaque account key shared across IP addresses and casing', function () {
    $first = IdentifierRequest::create('/', 'POST', ['email' => 'TEST@example.com'], [], [], ['REMOTE_ADDR' => '192.0.2.1']);
    $second = IdentifierRequest::create('/', 'POST', ['email' => 'test@example.com'], [], [], ['REMOTE_ADDR' => '192.0.2.2']);
    expect($first->throttleKey())->toBe($second->throttleKey())
        ->toStartWith('totp-login:request-account:')
        ->not->toContain('example.com');
});
