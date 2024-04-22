<?php

namespace Empuxa\TotpLogin\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;

class CreateAndSendLoginCode
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public $user, public readonly string $ip = '')
    {
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        $columns = config('totp-login.columns');
        $notification = config('totp-login.notification');
        $code = self::createCode();

        $this->user->{$columns['code']} = Hash::make($code);
        $this->user->{$columns['code_valid_until']} = now()->addSeconds(config('totp-login.code.expires_in'));
        $this->user->saveQuietly();

        $this->user->notify(new $notification($code, $this->ip));
    }

    /**
     * @throws \Exception
     */
    public static function createCode(): string
    {
        return str_pad(
            (string) random_int(0, (int) str_repeat('9', config('totp-login.code.length'))),
            config('totp-login.code.length'),
            '0',
            STR_PAD_LEFT,
        );
    }
}
