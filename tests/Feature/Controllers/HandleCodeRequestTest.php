<?php

namespace Empuxa\TotpLogin\Tests\Feature\Controllers;

use Empuxa\TotpLogin\Notifications\LoginCode;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

class HandleCodeRequestTest extends TestbenchTestCase
{
    use RefreshDatabase;

    public function test_cannot_login_with_wrong_code(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier')       => $user->{config('totp-login.columns.identifier')},
                config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [9, 9, 9, 9, 9, 9],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('code', __('controllers/session.store.error.totp_wrong', [
            'attempts_left' => config('totp-login.code.max_attempts') - 1,
        ]));

        $this->assertGuest();

        Notification::assertNothingSent();
    }

    public function test_cannot_login_with_expired_session(): void
    {
        Notification::fake();

        $user = $this->createUser([
            config('totp-login.columns.code_valid_until') => now()->subSecond(),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('code', __('controllers/session.store.error.expired'));

        $this->assertGuest();

        Notification::assertSentTo($user, LoginCode::class);
    }

    public function test_cannot_login_with_rate_limit(): void
    {
        Notification::fake();

        Config::set('totp-login.code.enable_throttling', true);

        $user = $this->createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $session = [
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ];

        for ($i = 0; $i < config('totp-login.code.max_attempts'); $i++) {
            $this
                ->withSession($session)
                ->post(route('totp-login.code.handle'), [
                    'code' => [9, 9, 9, 9, 9, 9],
                ]);
        }

        $response = $this
            ->withSession($session)
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('code', __('controllers/session.store.error.rate_limit', [
            'seconds' => RateLimiter::availableIn($user->{config('totp-login.columns.identifier')}),
        ]));

        $this->assertGuest();

        Notification::assertNothingSent();
    }

    private function loginWithCode(array $code): void
    {
        $formattedCode = '';

        collect($code)->each(function ($digit) use (&$formattedCode): void {
            $formattedCode .= $digit;
        });

        $user = $this->createUser([
            config('totp-login.columns.identifier')       => $formattedCode . '@example.com',
            config('totp-login.columns.code')             => Hash::make($formattedCode),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => $code,
            ]);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(config('totp-login.redirect'));

        $this->assertAuthenticatedAs($user);

        Notification::assertNothingSent();

        auth()->logout();
    }

    public function test_can_login_with_correct_code(): void
    {
        Notification::fake();

        $codes = [
            [3, 3, 3, 3, 3, 3],
            [1, 2, 3, 4, 5, 6],
            [9, 8, 7, 6, 5, 4],
            // There's been a bug, where PINs with leading zeros were not working
            [0, 5, 6, 2, 5, 5],
            [0, 0, 0, 0, 0, 0],
        ];

        foreach ($codes as $code) {
            $this->loginWithCode($code);
        }
    }

    public function test_can_login_through_disabled_rate_limit(): void
    {
        Notification::fake();

        Config::set('totp-login.code.enable_throttling', false);

        $user = $this->createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $session = [
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ];

        for ($i = 0; $i < config('totp-login.code.max_attempts'); $i++) {
            $this
                ->withSession($session)
                ->post(route('totp-login.code.handle'), [
                    'code' => [9, 9, 9, 9, 9, 9],
                ]);
        }

        $response = $this
            ->withSession($session)
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(config('totp-login.redirect'));

        $this->assertAuthenticatedAs($user);

        Notification::assertNothingSent();
    }

    public function test_can_login_with_superpin(): void
    {
        Notification::fake();

        Config::set('totp-login.superpin', 333333);

        $user = $this->createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [3, 3, 3, 3, 3, 3],
            ]);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(config('totp-login.redirect'));

        $this->assertAuthenticatedAs($user);

        Notification::assertNothingSent();
    }
}
