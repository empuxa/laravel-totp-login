<?php

namespace Empuxa\TotpLogin\Tests\Feature\Controllers;

use Empuxa\TotpLogin\Models\User;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;

class ShowCodeFormTest extends TestbenchTestCase
{
    public function test_cannot_render_pin_screen_because_of_missing_session(): void
    {
        $response = $this->get(route('totp-login.code.form'));

        $response->assertServerError();
    }

    public function test_can_render_pin_screen(): void
    {
        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => 'admin@example.com',
            ])
            ->get(route('totp-login.code.form'));

        $response->assertOk();
    }

    public function test_redirects_when_already_logged_in(): void
    {
        $this->withoutMiddleware();

        $user = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('totp-login.code.form'));

        $response->assertRedirect();
    }
}
