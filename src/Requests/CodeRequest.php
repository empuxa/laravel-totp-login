<?php

namespace Empuxa\TotpLogin\Requests;

use Empuxa\TotpLogin\Exceptions\MissingCode;
use Empuxa\TotpLogin\Exceptions\MissingSessionInformation;
use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
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
     * @throws \Throwable
     */
    public function authenticate(): void
    {
        throw_unless(session(config('totp-login.columns.identifier')), MissingSessionInformation::class);

        throw_unless(is_array($this->code), MissingCode::class);

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

        $codeMatchesSuperPin = $this->formattedCode === (string) config('totp-login.superpin.pin', false);

        if ($codeMatchesSuperPin && self::runsOnAllowedEnvironment(app()->environment())) {
            return;
        }

        if ($codeMatchesSuperPin && self::bypassesRestrictions($this->user->{config('totp-login.columns.identifier')})) {
            return;
        }

        if (Hash::check($this->formattedCode, $this->user->{config('totp-login.columns.code')})) {
            return;
        }

        RateLimiter::hit($this->throttleKey());

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
