<?php

use Empuxa\TotpLogin\Requests\CodeRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

describe('Database Row Locking', function () {
    it('successfully authenticates with database locking in place', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        expect(Auth::check())->toBeTrue()
            ->and(Auth::id())->toBe($user->id);
    });

    it('rejects sequential reuse after a completed login', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // First request succeeds
        $response1 = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response1->assertStatus(302);
        $response1->assertSessionHasNoErrors();

        expect(Auth::check())->toBeTrue();

        // Logout to simulate second attempt
        Auth::logout();

        // Second sequential request with same code should fail
        // because the code has been reset/invalidated by ResetLoginCode job
        $response2 = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        // Second attempt should fail (code was reset after first successful use)
        $response2->assertStatus(302);
        $response2->assertSessionHasErrors('code');
    });

    it('releases lock after successful validation', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        // After request completes, we should be able to query the user without blocking
        $freshUser = config('totp-login.model')::find($user->id);

        expect($freshUser)->not->toBeNull()
            ->and($freshUser->id)->toBe($user->id);
    });

    it('releases lock after failed validation', function () {
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                // Wrong code
                'code' => [9, 9, 9, 9, 9, 9],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('code');

        // After request completes, we should be able to query the user without blocking
        $freshUser = config('totp-login.model')::find($user->id);
        expect($freshUser)->not->toBeNull()
            ->and($freshUser->id)->toBe($user->id);
    });

    it('locks and validates the correct user in multi-user scenario', function () {
        $code = '123456';
        $user1 = createUser([
            config('totp-login.columns.identifier')       => 'user1@example.com',
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $user2 = createUser([
            config('totp-login.columns.identifier')       => 'user2@example.com',
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user1->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        expect(Auth::id())->toBe($user1->id);

        // Verify that user1 was authenticated (not user2)
        expect(Auth::user()->email)->toBe('user1@example.com');
    });

    it('handles database transaction rollback on validation failure', function () {
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                // Wrong code
                'code' => [9, 9, 9, 9, 9, 9],
            ]);

        // Validation fails, transaction should roll back
        $response->assertStatus(302);
        $response->assertSessionHasErrors('code');

        // User should not be authenticated
        expect(Auth::check())->toBeFalse();

        // User record should remain unchanged (no data corruption)
        $freshUser = config('totp-login.model')::find($user->id);

        expect($freshUser)->not->toBeNull();
    });

    it('keeps the code invalid after a completed login', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // First authentication succeeds
        $response1 = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response1->assertStatus(302);
        $response1->assertSessionHasNoErrors();

        expect(Auth::check())->toBeTrue();

        // Logout for second attempt
        Auth::logout();

        // Try to use the code again after the first request has completed.
        $response2 = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        // Second attempt should fail - the code has been reset/invalidated
        $response2->assertStatus(302);
        $response2->assertSessionHasErrors('code');

        // This covers sequential reuse only, not concurrent database access.
        expect(Auth::check())->toBeFalse();
    });
});

it('consumes the code before authentication returns to the controller', function () {
    $user = createUser();
    session(['email' => $user->email]);
    $request = CodeRequest::create('/login/code', 'POST', [
        'code' => str_split('123456'),
    ]);

    $request->authenticate();

    expect(now()->greaterThan($user->fresh()->login_totp_code_valid_until))->toBeTrue();
    expect(auth()->check())->toBeFalse();
    $second = CodeRequest::create('/login/code', 'POST', [
        'code' => str_split('123456'),
    ]);
    Notification::fake();
    expect(fn () => $second->authenticate())->toThrow(ValidationException::class);
});
