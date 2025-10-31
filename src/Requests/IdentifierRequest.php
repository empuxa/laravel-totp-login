<?php

namespace Empuxa\TotpLogin\Requests;

use Empuxa\TotpLogin\Events\IdentifierRateLimitContinued;
use Empuxa\TotpLogin\Events\IdentifierRateLimitExceeded;
use Empuxa\TotpLogin\Events\InvalidIdentifierFormat;
use Empuxa\TotpLogin\Events\UserNotFound;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentifierRequest extends BaseRequest
{
    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            config('totp-login.columns.identifier') => config('totp-login.identifier.validation'),
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $event = config('totp-login.events.invalid_identifier_format', InvalidIdentifierFormat::class);
        event(new $event(null, $this));

        parent::failedValidation($validator);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // This check might not be required if your validation rules already ensure
        // the user exists (e.g., via 'exists:users,email' rule in config).
        $this->checkIfUserExists();

        RateLimiter::clear($this->throttleKey());

        session()->forget('rate_limited_identifier_' . $this->throttleKey());
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (config('totp-login.identifier.enable_throttling', true) === false) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), config('totp-login.identifier.max_attempts') - 1)) {
            return;
        }

        $sessionKey = 'rate_limited_identifier_' . $this->throttleKey();

        // Check if this is the first time hitting the rate limit or a continued attempt
        if (! session()->has($sessionKey)) {
            // First time hitting rate limit - fire IdentifierRateLimitExceeded event
            $event = config('totp-login.events.identifier_rate_limit_exceeded', IdentifierRateLimitExceeded::class);
            event(new $event(null, $this));

            // Also fire the general Laravel Lockout event
            $lockoutEvent = config('totp-login.events.lockout', Lockout::class);
            event(new $lockoutEvent($this));

            // Mark that rate limit has been hit
            session()->put($sessionKey, now()->timestamp);
        } else {
            // Continued attempts after rate limit - fire IdentifierRateLimitContinued event
            $event = config('totp-login.events.identifier_rate_limit_continued', IdentifierRateLimitContinued::class);
            event(new $event(null, $this));
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            config('totp-login.columns.identifier') => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function checkIfUserExists(): void
    {
        if (! is_null($this->getUserModel())) {
            return;
        }

        RateLimiter::hit($this->throttleKey());

        $event = config('totp-login.events.user_not_found', UserNotFound::class);
        event(new $event(null, $this));

        throw ValidationException::withMessages([
            config('totp-login.columns.identifier') => __('auth.failed'),
        ]);
    }

    public function throttleKey(): string
    {
        // Throttle key includes IP address to prevent user enumeration attacks.
        // An attacker could otherwise test multiple identifiers from the same IP.
        return Str::lower($this->input(config('totp-login.columns.identifier'))) . '|' . $this->ip();
    }
}
