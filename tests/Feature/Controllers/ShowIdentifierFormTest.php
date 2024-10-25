<?php

namespace Empuxa\TotpLogin\Tests\Feature\Controllers;

use Empuxa\TotpLogin\Models\User;
use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShowIdentifierFormTest extends TestbenchTestCase
{
    use RefreshDatabase;

    public function test_can_render_login_screen(): void
    {
        $response = $this->get(route('totp-login.identifier.form'));

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

        $response = $this->get(route('totp-login.identifier.form'));

        $response->assertRedirect();
    }
}
