<?php

use Empuxa\TotpLogin\Events\CodeExpired;
use Empuxa\TotpLogin\Events\CodeRateLimitExceeded;
use Empuxa\TotpLogin\Events\IdentifierRateLimitExceeded;
use Empuxa\TotpLogin\Events\IncorrectCode;
use Empuxa\TotpLogin\Events\InvalidCodeFormat;
use Empuxa\TotpLogin\Events\InvalidIdentifierFormat;
use Empuxa\TotpLogin\Events\MissingCodeData;
use Empuxa\TotpLogin\Events\MissingSessionInformation;
use Empuxa\TotpLogin\Events\UserNotFound;
use Empuxa\TotpLogin\Exceptions\MissingCode;
use Empuxa\TotpLogin\Requests\CodeRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

describe('Identifier Phase Failure Events', function () {
    beforeEach(function () {
        Event::fake();
    });

    it('dispatches InvalidIdentifierFormat event on invalid email format', function () {
        $response = $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => 'invalid-email-format',
        ]);

        $response->assertSessionHasErrors();

        $event = config('totp-login.events.invalid_identifier_format', InvalidIdentifierFormat::class);
        Event::assertDispatched($event, function ($e) {
            return $e->user === null && $e->request !== null;
        });
    });

    it('dispatches UserNotFound event when user does not exist', function () {
        $response = $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => 'nonexistent@example.com',
        ]);

        $response->assertSessionHasErrors();

        $event = config('totp-login.events.user_not_found', UserNotFound::class);
        Event::assertDispatched($event, function ($e) {
            return $e->user === null && $e->request !== null;
        });
    });

    it('dispatches IdentifierRateLimitExceeded event when rate limit is hit', function () {
        Config::set('totp-login.identifier.enable_throttling', true);

        for ($i = 0; $i < config('totp-login.identifier.max_attempts'); $i++) {
            $this->post(route('totp-login.identifier.handle'), [
                config('totp-login.columns.identifier') => 'nonexistent@example.com',
            ]);
        }

        $event = config('totp-login.events.identifier_rate_limit_exceeded', IdentifierRateLimitExceeded::class);
        Event::assertDispatched($event, function ($e) {
            return $e->user === null && $e->request !== null;
        });
    });

    it('dispatches both IdentifierRateLimitExceeded and Lockout events for backward compatibility', function () {
        Config::set('totp-login.identifier.enable_throttling', true);

        for ($i = 0; $i < config('totp-login.identifier.max_attempts'); $i++) {
            $this->post(route('totp-login.identifier.handle'), [
                config('totp-login.columns.identifier') => 'nonexistent@example.com',
            ]);
        }

        // New event
        $event = config('totp-login.events.identifier_rate_limit_exceeded', IdentifierRateLimitExceeded::class);
        Event::assertDispatched($event);

        // Old event for backward compatibility
        $lockoutEvent = config('totp-login.events.lockout');
        Event::assertDispatched($lockoutEvent);
    });
});

describe('Code Phase Failure Events', function () {
    beforeEach(function () {
        Event::fake();
    });

    it('dispatches MissingSessionInformation event when session is missing', function () {
        $response = $this->post(route('totp-login.code.handle'), [
            'code' => [1, 2, 3, 4, 5, 6],
        ]);

        // MissingSessionInformation exception
        $response->assertStatus(500);

        $event = config('totp-login.events.missing_session_information', MissingSessionInformation::class);
        Event::assertDispatched($event, function ($e) {
            return $e->user === null && $e->request !== null;
        });
    });

    it('dispatches MissingCodeData event when code data is missing after validation', function () {
        $user = createUser();

        // This tests the MissingCodeData event which fires when validation passes
        // but the code property is somehow not an array when authenticate() is called.
        // We use a mock to simulate this edge case scenario.
        Event::fake();

        // Mock the CodeRequest to simulate input('code') returning null
        $mock = Mockery::mock(CodeRequest::class)->makePartial();
        $mock->shouldReceive('authorize')->andReturn(true);
        $mock->shouldReceive('rules')->andReturn([
            'code'   => 'required|array|size:6',
            'code.*' => 'required|numeric|digits:1',
        ]);
        $mock->shouldReceive('input')->with('code')->andReturn(null);

        // Set up session
        session([config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')}]);

        try {
            $mock->authenticate();
        } catch (MissingCode $e) {
            // Expected exception
        }

        $event = config('totp-login.events.missing_code_data', MissingCodeData::class);
        Event::assertDispatched($event, function ($e) {
            return $e->user === null && $e->request !== null;
        });
    });

    it('dispatches InvalidCodeFormat event when code format is invalid', function () {
        $user = createUser();

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                // Too short
                'code' => [1, 2, 3],
            ]);

        $response->assertSessionHasErrors();

        $event = config('totp-login.events.invalid_code_format', InvalidCodeFormat::class);
        Event::assertDispatched($event, function ($e) {
            return $e->user === null && $e->request !== null;
        });
    });

    it('dispatches CodeExpired event when code has expired', function () {
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->subSecond(),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertSessionHasErrors();

        $event = config('totp-login.events.code_expired', CodeExpired::class);
        Event::assertDispatched($event, function ($e) use ($user) {
            return $e->user !== null && $e->user->id === $user->id && $e->request !== null;
        });
    });

    it('dispatches IncorrectCode event when wrong code is entered', function () {
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                // Wrong code
                'code' => [9, 9, 9, 9, 9, 9],
            ]);

        $response->assertSessionHasErrors();

        $event = config('totp-login.events.incorrect_code', IncorrectCode::class);
        Event::assertDispatched($event, function ($e) use ($user) {
            return $e->user !== null && $e->user->id === $user->id && $e->request !== null;
        });
    });

    it('dispatches CodeRateLimitExceeded event when code rate limit is hit', function () {
        Config::set('totp-login.code.enable_throttling', true);

        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $session = [
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ];

        // Make max attempts with wrong code
        for ($i = 0; $i < config('totp-login.code.max_attempts'); $i++) {
            $this
                ->withSession($session)
                ->post(route('totp-login.code.handle'), [
                    'code' => [9, 9, 9, 9, 9, 9],
                ]);
        }

        $event = config('totp-login.events.code_rate_limit_exceeded', CodeRateLimitExceeded::class);
        Event::assertDispatched($event, function ($e) use ($user) {
            return $e->user !== null && $e->user->id === $user->id && $e->request !== null;
        });
    });
});

describe('Event Configuration', function () {
    it('respects event configuration from config file', function () {
        Event::fake();

        // Verify that the event configuration exists and matches expected defaults
        $invalidIdentifierEvent = config('totp-login.events.invalid_identifier_format');
        $userNotFoundEvent = config('totp-login.events.user_not_found');
        $identifierRateLimitEvent = config('totp-login.events.identifier_rate_limit_exceeded');
        $codeExpiredEvent = config('totp-login.events.code_expired');
        $incorrectCodeEvent = config('totp-login.events.incorrect_code');
        $codeRateLimitEvent = config('totp-login.events.code_rate_limit_exceeded');

        expect($invalidIdentifierEvent)->toBe(InvalidIdentifierFormat::class)
            ->and($userNotFoundEvent)->toBe(UserNotFound::class)
            ->and($identifierRateLimitEvent)->toBe(IdentifierRateLimitExceeded::class)
            ->and($codeExpiredEvent)->toBe(CodeExpired::class)
            ->and($incorrectCodeEvent)->toBe(IncorrectCode::class)
            ->and($codeRateLimitEvent)->toBe(CodeRateLimitExceeded::class);
    });
});
