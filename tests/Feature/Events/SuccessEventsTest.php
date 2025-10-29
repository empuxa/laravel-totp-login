<?php

use Empuxa\TotpLogin\Events\LoggedInViaTotp;
use Empuxa\TotpLogin\Events\LoginRequestViaTotp;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

describe('Success Events', function () {
    beforeEach(function () {
        Event::fake();
    });

    it('dispatches LoginRequestViaTotp event when user requests a code', function () {
        $user = createUser();

        $response = $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('totp-login.code.form'));

        $event = config('totp-login.events.login_request_via_totp', LoginRequestViaTotp::class);
        Event::assertDispatched($event, function ($e) use ($user) {
            return $e->user !== null
                && $e->user->id === $user->id
                && $e->request !== null;
        });
    });

    it('dispatches LoggedInViaTotp event when user successfully logs in', function () {
        $code = '123456';
        $user = createUser([
            config('totp-login.columns.code')             => Hash::make($code),
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [1, 2, 3, 4, 5, 6],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(config('totp-login.redirect'));

        $event = config('totp-login.events.logged_in_via_totp', LoggedInViaTotp::class);
        Event::assertDispatched($event, function ($e) use ($user) {
            return $e->user !== null
                && $e->user->id === $user->id
                && $e->request !== null;
        });
    });

    it('does not dispatch success events on failed attempts', function () {
        // Try to request code for non-existent user
        $this->post(route('totp-login.identifier.handle'), [
            config('totp-login.columns.identifier') => 'nonexistent@example.com',
        ]);

        $loginRequestEvent = config('totp-login.events.login_request_via_totp', LoginRequestViaTotp::class);
        Event::assertNotDispatched($loginRequestEvent);

        // Try to log in with wrong code
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $this
            ->withSession([
                config('totp-login.columns.identifier') => $user->{config('totp-login.columns.identifier')},
            ])
            ->post(route('totp-login.code.handle'), [
                'code' => [9, 9, 9, 9, 9, 9],
            ]);

        $loggedInEvent = config('totp-login.events.logged_in_via_totp', LoggedInViaTotp::class);
        Event::assertNotDispatched($loggedInEvent);
    });
});
