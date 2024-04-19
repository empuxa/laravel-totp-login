<?php

namespace Empuxa\TotpLogin\Tests\Feature\Controllers;

use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShowIdentifierFormTest extends TestbenchTestCase
{
    use RefreshDatabase;

    public function test_can_render_login_screen(): void
    {
        $response = $this->get(route('totp-login.identifier.form'));

        $response->assertStatus(200);
    }
}
