<?php

namespace Empuxa\TotpLogin\Requests;

use Empuxa\TotpLogin\Events\CodeExpired;
use Empuxa\TotpLogin\Events\CodeRateLimitExceeded;
use Empuxa\TotpLogin\Events\IncorrectCode;
use Empuxa\TotpLogin\Events\InvalidCodeFormat;
use Empuxa\TotpLogin\Events\MissingCodeData;
use Empuxa\TotpLogin\Events\MissingSessionInformation;
use Empuxa\TotpLogin\Exceptions\MissingCode as MissingCodeException;
use Empuxa\TotpLogin\Exceptions\MissingSessionInformation as MissingSessionInformationException;
use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CodeRequest extends BaseRequest
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $user;

    public ?string $formattedCode = '';

    public function rules(): array
    {
        return [
            'code'     => config('totp-login.code.validation'),
            'code.*'   => 'required|numeric|digits:1',
            'remember' => [
                'sometimes',
                // Boolean doesn't work here since it's a fake input
                Rule::in(['true', 'false']),
            ],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $event = config('totp-login.events.invalid_code_format', InvalidCodeFormat::class);
        event(new $event(null, $this));

        parent::failedValidation($validator);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
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

        // RACE CONDITION PREVENTION:
        // Use database transaction with pessimistic row locking for atomic code validation.
        // Without this, two concurrent requests could both validate the same code successfully.
        // The transaction + lockForUpdate() ensures only ONE request can validate at a time.
        DB::transaction(function (): void {
            // Acquire row-level lock on user record - held until transaction commits/rolls back.
            // The lock=true parameter enables database row locking, which is critical for preventing
            // race conditions where two simultaneous requests could validate the same code.
            $this->user = $this->getUserModel(session(config('totp-login.columns.identifier')), true);

            if (is_null($this->user)) {
                return;
            }

            $this->ensureIsNotRateLimited();
            $this->ensureCodeIsNotExpired();
            $this->validateCode();
        });

        RateLimiter::clear($this->throttleKey());
    }

    public function formatCode(): string
    {
        collect($this->input('code'))->each(function ($digit): void {
            $this->formattedCode .= $digit;
        });

        return $this->formattedCode;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (config('totp-login.code.enable_throttling', true) === false) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), config('totp-login.code.max_attempts') - 1)) {
            return;
        }

        $event = config('totp-login.events.code_rate_limit_exceeded', CodeRateLimitExceeded::class);
        event(new $event($this->user, $this));

        throw ValidationException::withMessages([
            // SECURITY NOTE: Displaying 'seconds' could help attackers time their attempts.
            // We expose it by default for better UX, but you can easily hide this information
            // by customizing the translation to show a generic message instead.
            'code' => __('totp-login::controller.handle_code_request.error.rate_limit', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureCodeIsNotExpired(): void
    {
        if (now() < $this->user->{config('totp-login.columns.code_valid_until')}) {
            return;
        }

        $event = config('totp-login.events.code_expired', CodeExpired::class);
        event(new $event($this->user, $this));

        // Send a new PIN for better UX.
        // SECURITY NOTE: This convenience feature could be abused for spam. Consider adding
        // additional rate limiting if you notice abuse patterns in your logs.
        CreateAndSendLoginCode::dispatch($this->user, $this->ip());

        throw ValidationException::withMessages([
            'code' => __('totp-login::controller.handle_code_request.error.expired'),
        ]);
    }

    /**
     * Checks if the superpin feature is allowed in the current environment.
     * Superpin provides a universal code for testing/development that bypasses normal validation.
     * SECURITY: Automatically disabled in production environments.
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
     * TIMING ATTACK PREVENTION:
     * This method ALWAYS performs a bcrypt hash check to prevent timing-based attacks.
     *
     * Without this protection, an attacker could:
     * - Detect when code is null (fast path = no hash check)
     * - Detect when superpin is used (fast path = early return)
     * - Distinguish between correct/incorrect codes based on execution time
     *
     * How we mitigate:
     * 1. Hash::check() is ALWAYS called, even if code is null (uses dummy hash)
     * 2. Superpin check happens AFTER the hash check, not before
     * 3. All validation paths execute the expensive bcrypt operation (~100-200ms)
     * 4. Response times are consistent regardless of the validation outcome
     *
     * This makes timing-based attacks impractical, as all code paths take similar time.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCode(): void
    {
        $this->formatCode();

        // TIMING ATTACK PREVENTION: Always perform hash check
        // Even if code is null, we use a dummy bcrypt hash to maintain consistent timing
        // This ensures all validation paths execute similar cryptographic operations
        $hashCheckResult = Hash::check(
            $this->formattedCode,
            $this->user->{config('totp-login.columns.code')}
                // dummy hash
                ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
        );

        // Check superpin AFTER hash check to maintain consistent timing
        // This prevents early returns that would create measurable timing differences
        $codeMatchesSuperPin = $this->formattedCode === (string) config('totp-login.superpin.pin', false);
        $superPinAllowed = $codeMatchesSuperPin && (
            self::runsOnAllowedEnvironment(app()->environment()) ||
            self::bypassesRestrictions($this->user->{config('totp-login.columns.identifier')})
        );

        // Validation succeeds if either hash matches or superpin is valid
        if ($hashCheckResult || $superPinAllowed) {
            return;
        }

        // Failed validation: increment rate limiter and fire event
        RateLimiter::hit($this->throttleKey());

        $event = config('totp-login.events.incorrect_code', IncorrectCode::class);
        event(new $event($this->user, $this));

        // SECURITY NOTE: Displaying 'attempts_left' could help attackers optimize their strategy.
        // We expose it by default for better UX, but you can easily hide this information
        // by customizing the translation to show a generic message instead.
        throw ValidationException::withMessages([
            'code' => __('totp-login::controller.handle_code_request.error.wrong_totp', [
                'attempts_left' => config('totp-login.code.max_attempts') - RateLimiter::attempts(
                    $this->throttleKey(),
                ),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        // Throttle key uses only the identifier (no IP) since the user is already identified.
        // This prevents legitimate users from being locked out if their IP changes.
        return Str::lower($this->user->{config('totp-login.columns.identifier')});
    }
}
