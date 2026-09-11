<?php

use Empuxa\TotpLogin\Requests\CodeRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

it('shares the code attempt limit across inputs resolving to the same account', function () {
    config(['totp-login.code.max_attempts' => 1]);
    $user = createUser(['email' => 'jose@example.com']);

    // Simulate a resolver whose collation treats these spellings as equal.
    // This tests account limiting, not production-database row locking.
    $makeRequest = fn () => new class($user) extends CodeRequest
    {
        public function __construct(private Model $resolvedUser)
        {
            parent::__construct();
        }

        public function getUserModel(?string $identifier = null, bool $lock = false): ?Model
        {
            return $this->resolvedUser->fresh();
        }
    };

    session(['email' => 'jose@example.com']);
    $first = $makeRequest();
    $first->merge(['code' => str_split('999999')]);
    expect(fn () => $first->authenticate())->toThrow(ValidationException::class);
    $key = $first->throttleKey();

    session(['email' => 'josé@example.com']);
    $second = $makeRequest();
    $second->merge(['code' => str_split('123456')]);
    expect(fn () => $second->authenticate())->toThrow(ValidationException::class);
    expect($second->throttleKey())->toBe($key);
    expect(RateLimiter::attempts($key))->toBe(1);
    expect(now()->lessThan($user->fresh()->login_totp_code_valid_until))->toBeTrue();
});
