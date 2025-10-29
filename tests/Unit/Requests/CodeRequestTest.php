<?php

use Empuxa\TotpLogin\Requests\CodeRequest;
use Illuminate\Support\Str;

describe('CodeRequest', function () {
    it('formats code from array to string', function () {
        $request = new CodeRequest();
        $request->code = [1, 2, 3, 4, 5, 6];

        $formatted = $request->formatCode();

        expect($formatted)->toBe('123456');
    });

    it('formats code with different digits', function () {
        $request = new CodeRequest();
        $request->code = [9, 8, 7, 6, 5, 4];

        $formatted = $request->formatCode();

        expect($formatted)->toBe('987654');
    });

    it('formats code with zeros', function () {
        $request = new CodeRequest();
        $request->code = [0, 0, 1, 2, 3, 4];

        $formatted = $request->formatCode();

        expect($formatted)->toBe('001234');
    });

    it('formats longer codes correctly', function () {
        $request = new CodeRequest();
        $request->code = [1, 2, 3, 4, 5, 6, 7, 8];

        $formatted = $request->formatCode();

        expect($formatted)->toBe('12345678');
    });

    it('generates throttle key using user identifier', function () {
        $user = createUser(['email' => 'TEST@EXAMPLE.COM']);

        $request = new CodeRequest();
        $request->user = $user;

        $throttleKey = $request->throttleKey();

        // Should be lowercase identifier
        expect($throttleKey)->toBe('test@example.com');
        expect($throttleKey)->toBe(Str::lower($user->{config('totp-login.columns.identifier')}));
    });

    it('throttle key is case-insensitive', function () {
        $user1 = createUser(['email' => 'TEST@example.com']);
        $user2 = createUser(['email' => 'test@EXAMPLE.com']);

        $request1 = new CodeRequest();
        $request1->user = $user1;

        $request2 = new CodeRequest();
        $request2->user = $user2;

        $key1 = $request1->throttleKey();
        $key2 = $request2->throttleKey();

        // Both should be lowercase
        expect($key1)->toBe('test@example.com');
        expect($key2)->toBe('test@example.com');
    });
});
