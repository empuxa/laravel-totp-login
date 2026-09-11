<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('executes the real hash check for each code validation path', function (string $scenario) {
    Notification::fake();
    $hash = Hash::make('123456');
    $user = createUser([
        'login_totp_code'             => $scenario === 'missing' ? null : $hash,
        'login_totp_code_valid_until' => now()->addMinutes(10),
    ]);
    $code = $scenario === 'correct' ? '123456' : '999999';
    if ($scenario === 'superpin') {
        config(['totp-login.superpin.pin' => $code, 'totp-login.superpin.environments' => ['testing']]);
    }

    // Wrap the configured hasher: assert execution without replacing cryptography.
    $hasher = Hash::getFacadeRoot();
    $expectedHash = $scenario === 'missing'
        ? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
        : $hash;
    Hash::shouldReceive('check')->once()->with($code, $expectedHash)
        ->andReturnUsing(fn ($plain, $stored) => $hasher->check($plain, $stored));

    $this->assertGuest();
    $response = $this->withSession(['email' => $user->email])->post(route('totp-login.code.handle'), [
        'code' => str_split($code),
    ]);
    if (in_array($scenario, ['correct', 'superpin'], true)) {
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    } else {
        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }
})->with(['correct', 'incorrect', 'missing', 'superpin']);
