<?php

use Empuxa\TotpLogin\Requests\IdentifierRequest;
use Illuminate\Support\Str;

describe('IdentifierRequest', function () {
    it('generates throttle key with identifier and IP', function () {
        $request = Mockery::mock(IdentifierRequest::class)->makePartial();
        $request->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('test@example.com');
        $request->shouldReceive('ip')
            ->andReturn('192.168.1.1');

        $throttleKey = $request->throttleKey();

        expect($throttleKey)->toBe('test@example.com|192.168.1.1');
    });

    it('throttle key is case-insensitive for identifier', function () {
        $request = Mockery::mock(IdentifierRequest::class)->makePartial();
        $request->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('TEST@EXAMPLE.COM');
        $request->shouldReceive('ip')
            ->andReturn('192.168.1.1');

        $throttleKey = $request->throttleKey();

        expect($throttleKey)->toBe('test@example.com|192.168.1.1');
    });

    it('includes IP address in throttle key', function () {
        $request1 = Mockery::mock(IdentifierRequest::class)->makePartial();
        $request1->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('test@example.com');
        $request1->shouldReceive('ip')
            ->andReturn('192.168.1.1');

        $request2 = Mockery::mock(IdentifierRequest::class)->makePartial();
        $request2->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('test@example.com');
        $request2->shouldReceive('ip')
            ->andReturn('10.0.0.1');

        $key1 = $request1->throttleKey();
        $key2 = $request2->throttleKey();

        // Same identifier but different IPs should have different throttle keys
        expect($key1)->not->toBe($key2);
        expect($key1)->toBe('test@example.com|192.168.1.1');
        expect($key2)->toBe('test@example.com|10.0.0.1');
    });

    it('throttle key format matches expected pattern', function () {
        $request = Mockery::mock(IdentifierRequest::class)->makePartial();
        $request->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('user@domain.com');
        $request->shouldReceive('ip')
            ->andReturn('127.0.0.1');

        $throttleKey = $request->throttleKey();

        expect($throttleKey)->toMatch('/^.+\|.+$/'); // Contains pipe separator
        expect(explode('|', $throttleKey))->toHaveCount(2); // Has exactly 2 parts
    });
});
