<?php

namespace Empuxa\TotpLogin\Requests;

use Empuxa\TotpLogin\Events\CodeExpired;
use Empuxa\TotpLogin\Events\CodeRateLimitExceeded;
use Empuxa\TotpLogin\Events\IncorrectCode;
use Empuxa\TotpLogin\Events\InvalidCodeFormat;
use Empuxa\TotpLogin\Events\MissingCodeData as MissingCodeDataEvent;
use Empuxa\TotpLogin\Events\MissingSessionInformation as MissingSessionInformationEvent;
use Empuxa\TotpLogin\Exceptions\MissingCode;
use Empuxa\TotpLogin\Exceptions\MissingSessionInformation;
use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Illuminate\Contracts\Validation\Validator;
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
            $event = config('totp-login.events.missing_session_information', MissingSessionInformationEvent::class);
            event(new $event(null, $this));

            throw new MissingSessionInformation;
        }

        if (! is_array($this->code)) {
            $event = config('totp-login.events.missing_code_data', MissingCodeDataEvent::class);
            event(new $event(null, $this));

            throw new MissingCode;
        }

        $this->user = $this->getUserModel(session(config('totp-login.columns.identifier')));

        if (is_null($this->user)) {
            return;
        }

        $this->ensureIsNotRateLimited();
        $this->ensureCodeIsNotExpired();
        $this->validateCode();

        RateLimiter::clear($this->throttleKey());
    }

    public function formatCode(): string
    {
        collect($this->code)->each(function ($digit): void {
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

        // Send a new PIN for better UX
        CreateAndSendLoginCode::dispatch($this->user, $this->ip());

        throw ValidationException::withMessages([
            'code' => __('totp-login::controller.handle_code_request.error.expired'),
        ]);
    }

    public static function runsOnAllowedEnvironment(?string $environment = null): bool
    {
        return filled($environment)
               && $environment !== 'production'
               && in_array($environment, config('totp-login.superpin.environments', ['local', 'testing']), true);
    }

    public static function bypassesRestrictions(?string $identifier = null): bool
    {
        return filled($identifier)
               && in_array($identifier, config('totp-login.superpin.bypassing_identifiers', []), true);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCode(): void
    {
        $this->formatCode();

        $codeIsSuperPin = $this->formattedCode === (string) config('totp-login.superpin.pin', false);

        if ($codeIsSuperPin && self::runsOnAllowedEnvironment(app()->environment())) {
            return;
        }

        if ($codeIsSuperPin && self::bypassesRestrictions($this->user->{config('totp-login.columns.identifier')})) {
            return;
        }

        if (Hash::check($this->formattedCode, $this->user->{config('totp-login.columns.code')})) {
            return;
        }

        RateLimiter::hit($this->throttleKey());

        $event = config('totp-login.events.incorrect_code', IncorrectCode::class);
        event(new $event($this->user, $this));

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
        return Str::lower($this->user->{config('totp-login.columns.identifier')});
    }
}
