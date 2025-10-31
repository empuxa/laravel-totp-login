<?php

use Illuminate\Support\Facades\Hash;

describe('Timing Attack Protection', function () {
    it('normalizes response time for code validation regardless of correctness', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // Measure time for correct code
        $start1 = microtime(true);
        $response1 = $this->withSession([
            config('totp-login.columns.identifier') => $user->email,
        ])->post(route('totp-login.code.handle'), [
            'code' => [1, 2, 3, 4, 5, 6],
        ]);
        $time1 = microtime(true) - $start1;

        // Measure time for incorrect code
        $start2 = microtime(true);
        $response2 = $this->withSession([
            config('totp-login.columns.identifier') => $user->email,
        ])->post(route('totp-login.code.handle'), [
            'code' => [9, 9, 9, 9, 9, 9],
        ]);
        $time2 = microtime(true) - $start2;

        // Both requests should complete (one success, one failure)
        expect($response1->status())->toBe(302)
            ->and($response2->status())->toBe(302);

        // The timing difference should be minimal (< 150ms tolerance for test environment)
        // Hash::check() is always called regardless of code correctness
        // Note: Perfect timing equality is impossible, but we reduce exploitability significantly
        $timeDifference = abs($time1 - $time2);

        expect($timeDifference)->toBeLessThan(
            0.15,
            "Timing difference should be minimal to prevent timing attacks (was {$timeDifference}s)"
        );
    });

    it('performs hash check even when using superpin', function () {
        config(['totp-login.superpin.pin' => '999999']);
        config(['totp-login.superpin.environments' => ['testing']]);

        $userWithSuperpin = createUser([
            config('totp-login.columns.code')             => Hash::make('123456'),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $userWithCorrectCode = createUser([
            config('totp-login.columns.identifier')       => 'test2@example.com',
            config('totp-login.columns.code')             => Hash::make('123456'),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // Measure time for superpin (should include hash check)
        $start1 = microtime(true);
        $response1 = $this->withSession([
            config('totp-login.columns.identifier') => $userWithSuperpin->email,
        ])->post(route('totp-login.code.handle'), [
            'code' => [9, 9, 9, 9, 9, 9], // Superpin
        ]);
        $time1 = microtime(true) - $start1;

        // Measure time for correct code (definitely includes hash check)
        $start2 = microtime(true);
        $response2 = $this->withSession([
            config('totp-login.columns.identifier') => $userWithCorrectCode->email,
        ])->post(route('totp-login.code.handle'), [
            'code' => [1, 2, 3, 4, 5, 6], // Correct code
        ]);
        $time2 = microtime(true) - $start2;

        // Both should succeed
        $response1->assertStatus(302);
        $response1->assertSessionHasNoErrors();
        $response2->assertStatus(302);
        $response2->assertSessionHasNoErrors();

        // Timing should be similar - if superpin bypassed hash check, it would be noticeably faster
        $timeDifference = abs($time1 - $time2);
        expect($timeDifference)->toBeLessThan(
            0.15,
            "Timing difference should be minimal, proving hash check runs even with superpin (was {$timeDifference}s)",
        );
    });

    it('always performs hash check in code validation', function () {
        $userWithoutCode = createUser([
            config('totp-login.columns.code')             => null, // No code set
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $userWithCode = createUser([
            config('totp-login.columns.identifier')       => 'test2@example.com',
            config('totp-login.columns.code')             => Hash::make('123456'),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        // Measure time for null code (should use dummy hash)
        $start1 = microtime(true);
        $response1 = $this->withSession([
            config('totp-login.columns.identifier') => $userWithoutCode->email,
        ])->post(route('totp-login.code.handle'), [
            'code' => [1, 2, 3, 4, 5, 6],
        ]);
        $time1 = microtime(true) - $start1;

        // Measure time for wrong code (definitely uses hash check)
        $start2 = microtime(true);
        $response2 = $this->withSession([
            config('totp-login.columns.identifier') => $userWithCode->email,
        ])->post(route('totp-login.code.handle'), [
            'code' => [9, 9, 9, 9, 9, 9],
        ]);
        $time2 = microtime(true) - $start2;

        // Both should fail
        $response1->assertStatus(302);
        $response1->assertSessionHasErrors('code');
        $response2->assertStatus(302);
        $response2->assertSessionHasErrors('code');
        expect(auth()->check())->toBeFalse();

        // Timing should be similar - if null code skipped hash check, it would be noticeably faster
        $timeDifference = abs($time1 - $time2);

        expect($timeDifference)->toBeLessThan(
            0.15,
            "Timing difference should be minimal, proving dummy hash is used when code is null (was {$timeDifference}s)"
        );
    });

    it('maintains timing protection with rate limiting enabled', function () {
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make('123456'),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $timings = [];

        // Make multiple failed attempts
        for ($i = 0; $i < 3; $i++) {
            $start = microtime(true);
            $this->withSession([
                config('totp-login.columns.identifier') => $user->email,
            ])->post(route('totp-login.code.handle'), [
                'code' => [9, 9, 9, 9, 9, 9],
            ]);
            $timings[] = microtime(true) - $start;
        }

        // All attempts should have similar timing despite rate limiter incrementing
        $maxTiming = max($timings);
        $minTiming = min($timings);
        $timingSpread = $maxTiming - $minTiming;

        expect($timingSpread)->toBeLessThan(
            0.15,
            "Timing spread across attempts should be minimal (was {$timingSpread}s)"
        );
    });
});
