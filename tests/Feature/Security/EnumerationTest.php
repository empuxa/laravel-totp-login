<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('returns the same identifier response for existing missing and limited accounts', function () {
    Notification::fake();
    $user = createUser();
    $known = $this->post(route('totp-login.identifier.handle'), ['email' => $user->email]);
    $message = session('message');
    $missing = $this->post(route('totp-login.identifier.handle'), ['email' => 'missing@example.com']);
    $missing->assertRedirect($known->headers->get('Location'))->assertSessionHasNoErrors();
    expect(session('message'))->toBe($message);
    for ($i = 0; $i < 10; $i++) {
        $this->post(route('totp-login.identifier.handle'), ['email' => $user->email])
            ->assertRedirect($known->headers->get('Location'))->assertSessionHasNoErrors();
        expect(session('message'))->toBe($message);
    }
    Notification::assertCount(1);
});

it('returns the same code error for missing accounts and incorrect or expired codes', function () {
    Notification::fake();
    $user = createUser();
    foreach ([$user->email, 'missing@example.com'] as $email) {
        $this->withSession(['email' => $email])->post(route('totp-login.code.handle'), ['code' => str_split('999999')])
            ->assertSessionHasErrors(['code' => __('totp-login::controller.handle_code_request.error.invalid')]);
        $this->assertGuest();
    }
    $user->login_totp_code_valid_until = now()->subMinute();
    $user->saveQuietly();
    $this->withSession(['email' => $user->email])->post(route('totp-login.code.handle'), ['code' => str_split('999999')])
        ->assertSessionHasErrors(['code' => __('totp-login::controller.handle_code_request.error.invalid')]);
});

it('rejects missing accounts and missing hashes with the configured hasher', function (string $driver) {
    config(['hashing.driver' => $driver]);
    Hash::clearResolvedInstance('hash');
    app()->forgetInstance('hash');
    $user = createUser(['login_totp_code' => null]);
    foreach ([$user->email, 'missing@example.com'] as $email) {
        $this->withSession(['email' => $email])->post(route('totp-login.code.handle'), ['code' => str_split('999999')])
            ->assertSessionHasErrors(['code' => __('totp-login::controller.handle_code_request.error.invalid')]);
        $this->assertGuest();
    }
})->with(['bcrypt', 'argon2id']);
