<?php

use Empuxa\TotpLogin\Requests\CodeRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

it('validates and accepts configured code lengths including leading zeros', function (string $code) {
    config(['totp-login.code.length' => strlen($code)]);
    $user = createUser(['login_totp_code' => Hash::make($code)]);
    $this->withSession(['email' => $user->email])->post(route('totp-login.code.handle'), [
        'code' => str_split($code),
    ])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
})->with(['0123', '012345', '01234567']);

it('preserves explicit validation rules', function () {
    config(['totp-login.code.length' => 8, 'totp-login.code.validation' => 'required|array|size:4']);
    expect(Validator::make(['code' => str_split('0123')], (new CodeRequest)->rules())->passes())->toBeTrue();
    expect(Validator::make(['code' => str_split('01234567')], (new CodeRequest)->rules())->passes())->toBeFalse();
});

it('allows exactly the configured number of incorrect codes', function (int $limit) {
    config(['totp-login.code.max_attempts' => $limit]);
    $user = createUser();
    session(['email' => $user->email]);
    $request = new CodeRequest;
    $request->user = $user;
    $key = $request->throttleKey();
    for ($i = 1; $i <= $limit; $i++) {
        $this->post(route('totp-login.code.handle'), ['code' => str_split('999999')])->assertSessionHasErrors('code');
        expect(RateLimiter::attempts($key))->toBe($i);
    }
    $this->post(route('totp-login.code.handle'), ['code' => str_split('123456')])->assertSessionHasErrors('code');
    $this->assertGuest();
    expect(RateLimiter::attempts($key))->toBe($limit);
})->with([1, 5]);

it('allows exactly the configured number of identifier requests', function (int $limit) {
    Notification::fake();
    config(['totp-login.identifier.max_attempts' => $limit, 'totp-login.identifier.resend_cooldown' => 1]);
    $user = createUser();
    for ($i = 0; $i < $limit + 1; $i++) {
        $this->post(route('totp-login.identifier.handle'), ['email' => $user->email])->assertSessionHasNoErrors();
        $this->travel(2)->seconds();
    }
    Notification::assertCount($limit);
})->with([1, 5]);
