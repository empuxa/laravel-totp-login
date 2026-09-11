<?php

use Empuxa\TotpLogin\Tests\TestbenchTestCase;

require __DIR__ . '/../../vendor/autoload.php';

putenv('APP_KEY=base64:' . base64_encode(str_repeat('x', 32)));
putenv('MAIL_MAILER=array');

class BrowserFixtures extends TestbenchTestCase
{
    public function renderFixtures(): void
    {
        $this->setUp();
        $directory = __DIR__ . '/../../build/browser';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory . '/identifier.html', $this->get(route('totp-login.identifier.form'))->getContent());
        foreach ([6, 8] as $length) {
            config(['totp-login.code.length' => $length]);
            file_put_contents($directory . '/code-' . $length . '.html', $this->withSession(['email' => 'test@example.com'])
                ->get(route('totp-login.code.form'))->getContent());
        }
    }
}

(new BrowserFixtures('renderFixtures'))->renderFixtures();
