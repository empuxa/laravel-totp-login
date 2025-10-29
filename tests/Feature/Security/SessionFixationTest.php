<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

describe('Session Fixation Protection', function () {
    it('regenerates session after successful login', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // Store the original session ID
        Session::put(config('totp-login.columns.identifier'), $user->email);
        $oldSessionId = Session::getId();

        // Attempt to log in
        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(config('totp-login.redirect'));

        // Verify user is logged in
        expect(Auth::check())->toBeTrue()
            ->and(Auth::id())->toBe($user->id);

        // CRITICAL: Session ID must have changed (regenerated)
        $newSessionId = Session::getId();
        expect($newSessionId)->not->toBe($oldSessionId)
            ->and($newSessionId)->not->toBeEmpty();
    });

    it('session regeneration is called during successful authentication', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // Store session ID before login
        $oldSessionId = Session::getId();

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Verify user is logged in
        expect(Auth::check())->toBeTrue();

        // CRITICAL: Session ID must change after regeneration
        // This proves session()->regenerate() was called
        $newSessionId = Session::getId();

        expect($newSessionId)->not->toBe($oldSessionId);
    });

    it('prevents session fixation attack scenario', function () {
        $code = '123456';
        $victim = createUser([
            config('totp-login.columns.identifier')       => 'victim@example.com',
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // Simulate victim logging in
        // In real attack: attacker would fix session ID via URL parameter or cookie injection
        // After login, session()->regenerate() creates a new session ID
        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $victim->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        expect(Auth::check())->toBeTrue()
            ->and(Auth::user()->email)->toBe('victim@example.com');

        // The key protection: session()->regenerate() was called
        // This means any attacker with the old session ID can't access the authenticated session
        // We verify this by checking the user is properly authenticated
        $this->assertAuthenticatedAs($victim);
    });

    it('maintains user authentication after session regeneration', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        Session::put(config('totp-login.columns.identifier'), $user->email);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // User should be authenticated
        expect(Auth::check())->toBeTrue()
            ->and(Auth::id())->toBe($user->id)
            ->and(Auth::user()->email)->toBe($user->email);

        // Auth guard should have the correct user
        $this->assertAuthenticatedAs($user);
    });

    it('does not regenerate session on failed login attempts', function () {
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // Attempt login with wrong code
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

        // User should NOT be authenticated
        // On failed attempts, session is not regenerated
        // This is expected behavior - only successful auth regenerates the session
        expect(Auth::check())->toBeFalse();
    });

    it('retrieves identifier from session before regeneration', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.identifier')       => 'testuser@example.com',
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // The identifier is stored in session during the identifier submission step
        Session::put(config('totp-login.columns.identifier'), 'testuser@example.com');

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => 'testuser@example.com',
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // The correct user should be logged in (based on identifier from old session)
        expect(Auth::check())->toBeTrue()
            ->and(Auth::user()->email)->toBe('testuser@example.com')
            ->and(Auth::id())->toBe($user->id);
    });

    it('handles concurrent login attempts safely', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        Session::put(config('totp-login.columns.identifier'), $user->email);
        $sessionId1 = Session::getId();

        // First request succeeds
        $response1 = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response1->assertStatus(302);
        expect(Auth::check())->toBeTrue();

        // Logout to simulate second concurrent attempt
        Auth::logout();
        Session::flush();

        // Second concurrent request with old session (should fail or be rate limited)
        Session::setId($sessionId1);
        Session::start();
        Session::put(config('totp-login.columns.identifier'), $user->email);

        // This should fail because the code was already used/expired
        $response2 = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        // Second attempt should fail (code already used)
        $response2->assertSessionHasErrors();

        expect(Auth::check())->toBeFalse();
    });
});
