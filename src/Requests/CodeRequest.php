<?php

namespace Empuxa\TotpLogin\Requests;

use Empuxa\TotpLogin\Events\CodeExpired;
use Empuxa\TotpLogin\Events\CodeRateLimitContinued;
use Empuxa\TotpLogin\Events\CodeRateLimitExceeded;
use Empuxa\TotpLogin\Events\IncorrectCode;
use Empuxa\TotpLogin\Events\InvalidCodeFormat;
use Empuxa\TotpLogin\Events\MissingCodeData;
use Empuxa\TotpLogin\Events\MissingSessionInformation;
use Empuxa\TotpLogin\Exceptions\MissingCode as MissingCodeException;
use Empuxa\TotpLogin\Exceptions\MissingSessionInformation as MissingSessionInformationException;
use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Empuxa\TotpLogin\Jobs\ResetLoginCode;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CodeRequest extends BaseRequest
{
    /**
     * @var Model
     */
    public $user;

    public ?string $formattedCode = '';

    public function rules(): array
    {
        return [
            'code'     => config('totp-login.code.validation') ?? 'required|array|size:' . config('totp-login.code.length'),
            'code.*'   => 'required|numeric|digits:1',
            'remember' => [
                'sometimes',
                // Boolean doesn't work here since it's a fake input
                Rule::in(['true', 'false']),
            ],
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $event = config('totp-login.events.invalid_code_format', InvalidCodeFormat::class);
        event(new $event(null, $this));

        parent::failedValidation($validator);
    }

    /**
     * @throws ValidationException
     * @throws \Throwable
     */
    public function authenticate(): void
    {
        if (! session(config('totp-login.columns.identifier'))) {
            $event = config('totp-login.events.missing_session_information', MissingSessionInformation::class);
            event(new $event(null, $this));

            throw new MissingSessionInformationException;
        }

        if (! is_array($this->input('code'))) {
            $event = config('totp-login.events.missing_code_data', MissingCodeData::class);
            event(new $event(null, $this));

            throw new MissingCodeException;
        }

        // Validate and consume under the same row lock on the model's connection.
        $expired = (new (config('totp-login.model')))->getConnection()->transaction(function (): bool {
            $this->user = $this->getUserModel(session(config('totp-login.columns.identifier')), true);

            $this->ensureIsNotRateLimited();

            if (is_null($this->user)) {
                $this->validateCode();
            }

            if (now() >= $this->user->{config('totp-login.columns.code_valid_until')}) {
                return true;
            }

            $this->validateCode();

            ResetLoginCode::dispatchSync($this->user);

            return false;
        });

        if ($expired) {
            $this->ensureCodeIsNotExpired();
        }

        RateLimiter::clear($this->throttleKey());

        session()->forget('rate_limited_code_' . $this->throttleKey());
    }

    public function formatCode(): string
    {
        return $this->formattedCode = implode('', $this->input('code'));
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (config('totp-login.code.enable_throttling', true) === false) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), config('totp-login.code.max_attempts'))) {
            return;
        }

        $sessionKey = 'rate_limited_code_' . $this->throttleKey();

        // Check if this is the first time hitting the rate limit or a continued attempt
        if (! session()->has($sessionKey)) {
            // First time hitting rate limit - fire CodeRateLimitExceeded event
            $event = config('totp-login.events.code_rate_limit_exceeded', CodeRateLimitExceeded::class);
            event(new $event($this->user, $this));

            // Also fire the general Laravel Lockout event
            $lockoutEvent = config('totp-login.events.lockout', Lockout::class);
            event(new $lockoutEvent($this));

            // Mark that rate limit has been hit
            session()->put($sessionKey, now()->timestamp);
        } else {
            // Continued attempts after rate limit - fire CodeRateLimitContinued event
            $event = config('totp-login.events.code_rate_limit_continued', CodeRateLimitContinued::class);
            event(new $event($this->user, $this));
        }

        throw ValidationException::withMessages([
            'code' => __('totp-login::controller.handle_code_request.error.invalid'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureCodeIsNotExpired(): void
    {
        if (now() < $this->user->{config('totp-login.columns.code_valid_until')}) {
            return;
        }

        $event = config('totp-login.events.code_expired', CodeExpired::class);
        event(new $event($this->user, $this));

        $identifierRequest = IdentifierRequest::create('/', 'POST', [
            config('totp-login.columns.identifier') => $this->user->{config('totp-login.columns.identifier')},
        ], [], [], ['REMOTE_ADDR' => $this->ip()]);

        try {
            $identifierRequest->ensureIsNotRateLimited();
            CreateAndSendLoginCode::dispatchSync($this->user, $this->ip(), true);
        } catch (ValidationException) {
            // Keep the expired-code response neutral when resend is limited.
        }

        throw ValidationException::withMessages([
            'code' => __('totp-login::controller.handle_code_request.error.invalid'),
        ]);
    }

    /**
     * Checks if the superpin feature is allowed in the current environment.
     * Superpin provides a universal code for testing/development that bypasses normal validation.
     * The environment list excludes production; identifier exceptions are separate.
     */
    public static function runsOnAllowedEnvironment(?string $environment = null): bool
    {
        return filled($environment)
               && $environment !== 'production'
               && in_array($environment, config('totp-login.superpin.environments', ['local', 'testing']), true);
    }

    /**
     * Checks if a specific identifier (email/phone) is allowed to bypass environment restrictions.
     * Useful for allowing specific test accounts to use superpin even in staging environments.
     * Configure via 'totp-login.superpin.bypassing_identifiers' config array.
     */
    public static function bypassesRestrictions(?string $identifier = null): bool
    {
        return filled($identifier)
               && in_array($identifier, config('totp-login.superpin.bypassing_identifiers', []), true);
    }

    /**
     * Verify the hash before considering a superpin. A missing stored hash uses
     * a dummy with the configured algorithm. This is not a constant-time response
     * guarantee: missing hashes, expiry handling and notification delivery differ.
     *
     * @throws ValidationException
     */
    public function validateCode(): void
    {
        $this->formatCode();

        $storedHash = $this->user?->{config('totp-login.columns.code')};

        // A dummy must use the configured algorithm; bcrypt hashes are rejected
        // by Laravel's Argon drivers. Missing hashes never authenticate a user.
        $hashCheckResult = Hash::check(
            $this->formattedCode,
            $storedHash ?? Hash::make(Str::random(32)),
        );

        // Keep hash verification before the superpin decision.
        $codeMatchesSuperPin = $this->formattedCode === (string) config('totp-login.superpin.pin', false);
        $superPinAllowed = $codeMatchesSuperPin && (
            self::runsOnAllowedEnvironment(app()->environment()) ||
            self::bypassesRestrictions($this->user?->{config('totp-login.columns.identifier')})
        );

        // Validation succeeds if either hash matches or superpin is valid
        if ($this->user !== null && (($storedHash !== null && $hashCheckResult) || $superPinAllowed)) {
            return;
        }

        // Failed validation: increment rate limiter and fire event
        RateLimiter::hit($this->throttleKey());

        $event = config('totp-login.events.incorrect_code', IncorrectCode::class);
        event(new $event($this->user, $this));

        throw ValidationException::withMessages([
            'code' => __('totp-login::controller.handle_code_request.error.invalid'),
        ]);
    }

    public function throttleKey(): string
    {
        if ($this->user !== null) {
            $connection = $this->user->getConnection();
            $identity = json_encode([
                $connection->getName(),
                $connection->getDatabaseName(),
                $connection->getTablePrefix() . $this->user->getTable(),
                (string) $this->user->getKey(),
            ], JSON_THROW_ON_ERROR);

            return 'totp-login:code:account:' . hash('sha256', $identity);
        }

        return 'totp-login:code:identifier:' . hash('sha256', Str::lower(
            (string) session(config('totp-login.columns.identifier'))
        ));
    }
}
