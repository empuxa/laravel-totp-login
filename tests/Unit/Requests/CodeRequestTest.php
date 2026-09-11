<?php

use Empuxa\TotpLogin\Requests\CodeRequest;

describe('CodeRequest', function () {
    it('formats code from array to string', function () {
        $request = CodeRequest::create('/test', 'POST', ['code' => [1, 2, 3, 4, 5, 6]]);

        $formatted = $request->formatCode();

        expect($formatted)->toBe('123456');
    });

    it('formats code with different digits', function () {
        $request = CodeRequest::create('/test', 'POST', ['code' => [9, 8, 7, 6, 5, 4]]);

        $formatted = $request->formatCode();

        expect($formatted)->toBe('987654');
    });

    it('formats code with zeros', function () {
        $request = CodeRequest::create('/test', 'POST', ['code' => [0, 0, 1, 2, 3, 4]]);

        $formatted = $request->formatCode();

        expect($formatted)->toBe('001234');
    });

    it('formats longer codes correctly', function () {
        $request = CodeRequest::create('/test', 'POST', ['code' => [1, 2, 3, 4, 5, 6, 7, 8]]);

        $formatted = $request->formatCode();

        expect($formatted)->toBe('12345678');
    });

    it('keeps a stable account key when its identifier or the session input changes', function () {
        $user = createUser();
        $request = new CodeRequest;
        $request->user = $user;
        session(['email' => 'first@example.com']);
        $key = $request->throttleKey();
        $user->email = 'changed@example.com';
        session(['email' => 'second@example.com']);

        expect($request->throttleKey())->toBe($key)->toStartWith('totp-login:code:account:');
        expect($key)->not->toContain('example.com');
    });

    it('does not share limits between distinct accounts with similar identifiers', function () {
        $first = new CodeRequest;
        $first->user = createUser(['email' => 'TEST@example.com']);
        $second = new CodeRequest;
        $second->user = createUser(['email' => 'test@example.com']);
        expect($first->throttleKey())->not->toBe($second->throttleKey());
    });

    it('normalizes unknown identifiers without sharing known account keys', function () {
        $request = new CodeRequest;
        session(['email' => 'TEST@example.com']);
        $key = $request->throttleKey();
        session(['email' => 'test@example.com']);
        expect($request->throttleKey())->toBe($key)->toStartWith('totp-login:code:identifier:');
        $request->user = createUser(['email' => 'test@example.com']);
        expect($request->throttleKey())->not->toBe($key);
    });
});
