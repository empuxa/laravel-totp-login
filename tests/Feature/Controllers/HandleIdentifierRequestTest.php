<?php

namespace Empuxa\TotpLogin\Tests\Feature\Controllers;

use Empuxa\TotpLogin\Notifications\LoginCode;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class HandleIdentifierRequestTest extends TestbenchTestCase
{
    use RefreshDatabase;

    public function test_sends_email_to_user(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $response = $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => $user->email,
        ]);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(route('totp-login.code.form'));

        $this->assertGuest();

        Notification::assertSentTo($user, LoginCode::class);
    }

    public function test_does_not_send_email_to_user_with_wrong_email(): void
    {
        Notification::fake();

        $response = $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => 'not_existing@example.com',
        ]);

        $response->assertSessionHasErrors('email', __('auth.failed'));

        $this->assertGuest();

        Notification::assertNothingSent();
    }

    public function test_does_not_send_email_to_user_with_rate_limit(): void
    {
        Config::set('totp-login.identifier.enable_throttling', true);

        Event::fake();
        Notification::fake();

        for ($i = 0; $i < config('totp-login.identifier.max_attempts'); $i++) {
            $this->post(route('totp-login.identifier.handle'), [
                config('totp-login.columns.identifier') => 'non_existing@example.com',
            ]);
        }

        $event = config('totp-login.events.lockout');
        Event::assertDispatched($event);

        $this->assertGuest();

        Notification::assertNothingSent();
    }

    public function test_sends_email_to_user_through_disabled_rate_limit(): void
    {
        Config::set('totp-login.identifier.enable_throttling', false);

        Notification::fake();

        $user = $this->createUser();

        for ($i = 0; $i < config('totp-login.identifier.max_attempts'); $i++) {
            $this->post(route('totp-login.identifier.handle'), [
                config('totp-login.columns.identifier') => 'non_existing@example.com',
            ]);
        }

        $response = $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => $user->email,
        ]);

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(route('totp-login.code.form'));

        $this->assertGuest();

        Notification::assertSentTo($user, LoginCode::class);
    }
}
