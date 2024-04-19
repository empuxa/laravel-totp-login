<?php

namespace Empuxa\TotpLogin\Tests\Feature\Controllers;

use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShowCodeFormTest extends TestbenchTestCase
{
    use RefreshDatabase;

    public function test_cannot_render_pin_screen_because_of_missing_session(): void
    {
        $response = $this->get(route('totp-login.code.form'));

        $response->assertStatus(500);
    }

    public function test_can_render_pin_screen(): void
    {
        $response = $this
            ->withSession([
                config('totp-login.columns.identifier') => 'admin@example.com',
            ])
            ->get(route('totp-login.code.form'));

        $response->assertStatus(200);
    }
}
