<?php

namespace Empuxa\PinLogin\Requests;

use Empuxa\PinLogin\Exceptions\MissingPin;
use Empuxa\PinLogin\Exceptions\MissingSessionInformation;
use Empuxa\PinLogin\Jobs\CreateAndSendLoginPin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PinRequest extends BaseRequest
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $user;

    public ?string $formattedPin = '';

    public function rules(): array
    {
        return [
            'pin'      => config('pin-login.pin.validation'),
            'pin.*'    => 'required|numeric|digits:1',
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
        throw_unless(session(config('pin-login.columns.identifier')), MissingSessionInformation::class);

        throw_unless(is_array($this->pin), MissingPin::class);

        $this->user = $this->getUserModel(session(config('pin-login.columns.identifier')));

        if (is_null($this->user)) {
            return;
        }

        $this->ensureIsNotRateLimited();
        $this->ensurePinIsNotExpired();
        $this->validatePin();

        RateLimiter::clear($this->throttleKey());
    }

    public function formatPin(): int
    {
        collect($this->pin)->each(function ($digit): void {
            $this->formattedPin .= (int) $digit;
        });

        return $this->formattedPin;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (config('pin-login.pin.enable_throttling', true) === false) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), config('pin-login.pin.max_attempts') - 1)) {
            return;
        }

        throw ValidationException::withMessages([
            'pin' => __('pin-login::controller.handle_pin_request.error.rate_limit', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensurePinIsNotExpired(): void
    {
        if (now() < $this->user->{config('pin-login.columns.pin_valid_until')}) {
            return;
        }

        // Send a new PIN for better UX
        CreateAndSendLoginPin::dispatch($this->user, $this->ip());

        throw ValidationException::withMessages([
            'pin' => __('pin-login::controller.handle_pin_request.error.expired'),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validatePin(): void
    {
        $this->formatPin();

        if ($this->formattedPin === (string) config('pin-login.superpin') && ! app()->isProduction()) {
            return;
        }

        if (Hash::check($this->formattedPin, $this->user->{config('pin-login.columns.pin')})) {
            return;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'pin' => __('pin-login::controller.handle_pin_request.error.wrong_pin', [
                'attempts_left' => config('pin-login.pin.max_attempts') - RateLimiter::attempts(
                    $this->throttleKey(),
                ),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::lower($this->user->{config('pin-login.columns.identifier')});
    }
}
