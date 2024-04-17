<?php

namespace Empuxa\PinLogin\Tests\Feature\Controllers;

use Empuxa\PinLogin\Notifications\LoginPin;
use Empuxa\PinLogin\Tests\TestbenchTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

class HandlePinRequestTest extends TestbenchTestCase
{
    use RefreshDatabase;

    public function test_cannot_login_with_wrong_pin(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $response = $this
            ->withSession([
                config('pin-login.columns.identifier')      => $user->{config('pin-login.columns.identifier')},
                config('pin-login.columns.pin_valid_until') => now()->addMinutes(10),
            ])
            ->post(route('pin-login.pin.handle'), [
                'pin' => [9, 9, 9, 9, 9, 9],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('pin', __('controllers/session.store.error.pin_wrong', [
            'attempts_left' => config('pin-login.pin.max_attempts') - 1,
        ]));

        $this->assertGuest();

        Notification::assertNothingSent();
    }

    public function test_cannot_login_with_expired_session(): void
    {
        Notification::fake();

        $user = $this->createUser([
            config('pin-login.columns.pin_valid_until') => now()->subSecond(),
        ]);

        $response = $this
            ->withSession([
                config('pin-login.columns.identifier') => $user->{config('pin-login.columns.identifier')},
            ])
            ->post(route('pin-login.pin.handle'), [
                'pin' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('pin', __('controllers/session.store.error.expired'));

        $this->assertGuest();

        Notification::assertSentTo($user, LoginPin::class);
    }

    public function test_cannot_login_with_rate_limit(): void
    {
        Notification::fake();

        Config::set('pin-login.pin.enable_throttling', true);

        $user = $this->createUser([
            config('pin-login.columns.pin_valid_until') => now()->addMinutes(10),
        ]);

        $session = [
            config('pin-login.columns.identifier') => $user->{config('pin-login.columns.identifier')},
        ];

        for ($i = 0; $i < config('pin-login.pin.max_attempts'); $i++) {
            $this
                ->withSession($session)
                ->post(route('pin-login.pin.handle'), [
                    'pin' => [9, 9, 9, 9, 9, 9],
                ]);
        }

        $response = $this
            ->withSession($session)
            ->post(route('pin-login.pin.handle'), [
                'pin' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasErrors('pin', __('controllers/session.store.error.rate_limit', [
            'seconds' => RateLimiter::availableIn($user->{config('pin-login.columns.identifier')}),
        ]));

        $this->assertGuest();

        Notification::assertNothingSent();
    }

    private function loginWithPin(array $pin): void
    {
        $formattedPin = '';

        collect($pin)->each(function ($digit) use (&$formattedPin): void {
            $formattedPin .= (int) $digit;
        });

        $user = $this->createUser([
            config('pin-login.columns.identifier')      => $formattedPin . '@example.com',
            config('pin-login.columns.pin')             => Hash::make($formattedPin),
            config('pin-login.columns.pin_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('pin-login.columns.identifier') => $user->{config('pin-login.columns.identifier')},
            ])
            ->post(route('pin-login.pin.handle'), [
                'pin' => $pin,
            ]);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(config('pin-login.redirect'));

        $this->assertAuthenticatedAs($user);

        Notification::assertNothingSent();

        auth()->logout();
    }

    public function test_can_login_with_correct_pin(): void
    {
        Notification::fake();

        $pins = [
            [3, 3, 3, 3, 3, 3],
            [1, 2, 3, 4, 5, 6],
            [9, 8, 7, 6, 5, 4],
            // There's been a bug, where PINs with leading zeros were not working
            [0, 5, 6, 2, 5, 5],
            [0, 0, 0, 0, 0, 0],
        ];

        foreach ($pins as $pin) {
            $this->loginWithPin($pin);
        }
    }

    public function test_can_login_through_disabled_rate_limit(): void
    {
        Notification::fake();

        Config::set('pin-login.pin.enable_throttling', false);

        $user = $this->createUser([
            config('pin-login.columns.pin_valid_until') => now()->addMinutes(10),
        ]);

        $session = [
            config('pin-login.columns.identifier') => $user->{config('pin-login.columns.identifier')},
        ];

        for ($i = 0; $i < config('pin-login.pin.max_attempts'); $i++) {
            $this
                ->withSession($session)
                ->post(route('pin-login.pin.handle'), [
                    'pin' => [9, 9, 9, 9, 9, 9],
                ]);
        }

        $response = $this
            ->withSession($session)
            ->post(route('pin-login.pin.handle'), [
                'pin' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertStatus(302);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(config('pin-login.redirect'));

        $this->assertAuthenticatedAs($user);

        Notification::assertNothingSent();
    }

    public function test_can_login_with_superpin(): void
    {
        Notification::fake();

        Config::set('pin-login.superpin', 333333);

        $user = $this->createUser([
            config('pin-login.columns.pin_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('pin-login.columns.identifier') => $user->{config('pin-login.columns.identifier')},
            ])
            ->post(route('pin-login.pin.handle'), [
                'pin' => [3, 3, 3, 3, 3, 3],
            ]);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(config('pin-login.redirect'));

        $this->assertAuthenticatedAs($user);

        Notification::assertNothingSent();
    }
}
