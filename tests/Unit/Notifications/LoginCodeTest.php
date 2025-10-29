<?php

use Empuxa\TotpLogin\Notifications\LoginCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

describe('LoginCode Notification', function () {
    it('can be instantiated with code and IP', function () {
        $notification = new LoginCode('123456', '127.0.0.1');

        expect($notification)->toBeInstanceOf(LoginCode::class);
    });

    it('returns mail as notification channel', function () {
        $notification = new LoginCode('123456', '127.0.0.1');
        $user = createUser();

        $channels = $notification->via($user);

        expect($channels)->toBe(['mail']);
    });

    it('gets user timezone from timezone property', function () {
        $user = createUser();
        // Dynamically add timezone property
        $user->timezone = 'America/New_York';

        $timezone = LoginCode::getUserTimeZone($user);

        expect($timezone)->toBe('America/New_York');
    });

    it('gets user timezone from tz property when timezone is not set', function () {
        $user = createUser();
        // Dynamically add tz property
        $user->tz = 'Europe/London';

        $timezone = LoginCode::getUserTimeZone($user);

        expect($timezone)->toBe('Europe/London');
    });

    it('falls back to app timezone when user has no timezone', function () {
        Config::set('app.timezone', 'UTC');
        $user = createUser();

        $timezone = LoginCode::getUserTimeZone($user);

        expect($timezone)->toBe('UTC');
    });

    it('prefers timezone over tz property', function () {
        $user = createUser();
        // Dynamically add both properties
        $user->timezone = 'America/New_York';
        $user->tz = 'Europe/London';

        $timezone = LoginCode::getUserTimeZone($user);

        expect($timezone)->toBe('America/New_York');
    });

    it('generates a mail message with all required components', function () {
        $code = '123456';
        $ip = '192.168.1.1';
        $notification = new LoginCode($code, $ip);

        $user = createUser([
            'name'                                        => 'Test User',
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage)->toBeInstanceOf(MailMessage::class);
    });

    it('includes the code in the mail message', function () {
        $code = '123456';
        $ip = '127.0.0.1';
        $notification = new LoginCode($code, $ip);

        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        // Convert to array to inspect
        $data = $mailMessage->toArray();

        // Check that view data contains the split code
        expect($mailMessage->viewData)->toHaveKey('code');
        expect($mailMessage->viewData['code'])->toBe(['1', '2', '3', '4', '5', '6']);
    });

    it('includes the user in view data', function () {
        $notification = new LoginCode('123456', '127.0.0.1');
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->viewData)->toHaveKey('notifiable');
        expect($mailMessage->viewData['notifiable']->id)->toBe($user->id);
    });

    it('uses the correct markdown view', function () {
        $notification = new LoginCode('123456', '127.0.0.1');
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->markdown)->toBe('totp-login::notification');
    });

    it('includes action button with correct URL', function () {
        $notification = new LoginCode('123456', '127.0.0.1');
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->actionUrl)->toBe(route('totp-login.code.form'));
    });

    it('handles different code lengths', function () {
        Config::set('totp-login.code.length', 8);
        $code = '12345678';
        $notification = new LoginCode($code, '127.0.0.1');

        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->viewData['code'])->toBe(['1', '2', '3', '4', '5', '6', '7', '8']);
        expect($mailMessage->viewData['code'])->toHaveCount(8);
    });

    it('includes subject line', function () {
        $notification = new LoginCode('123456', '127.0.0.1');
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->subject)->not->toBeNull();
        expect($mailMessage->subject)->toBeString();
    });

    it('includes greeting', function () {
        $notification = new LoginCode('123456', '127.0.0.1');
        $user = createUser([
            'name'                                        => 'John Doe',
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage->greeting)->not->toBeNull();
        expect($mailMessage->greeting)->toBeString();
    });

    it('includes multiple lines of content', function () {
        $notification = new LoginCode('123456', '127.0.0.1');
        $user = createUser([
            config('totp-login.columns.code_valid_until') => now()->addMinutes(10),
        ]);

        $mailMessage = $notification->toMail($user);

        // The notification has 3 lines
        expect($mailMessage->introLines)->toHaveCount(3);
    });

    it('stores code and IP in notification instance', function () {
        $code = '987654';
        $ip = '10.0.0.1';
        $notification = new LoginCode($code, $ip);

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($notification);

        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        expect($codeProperty->getValue($notification))->toBe($code);

        $ipProperty = $reflection->getProperty('ip');
        $ipProperty->setAccessible(true);
        expect($ipProperty->getValue($notification))->toBe($ip);
    });
});
