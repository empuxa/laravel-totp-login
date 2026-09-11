<?php

namespace Empuxa\TotpLogin\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;

class CreateAndSendLoginCode
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public $user,
        public readonly string $ip = '',
        public readonly bool $onlyIfExpired = false,
    ) {}

    /**
     * Generates a random TOTP code, hashes it, stores it in the database, and sends it to the user.
     * This job is dispatched synchronously (dispatch_sync) to send the code immediately.
     *
     * @throws \Exception
     */
    public function handle(): void
    {
        $connection = $this->user->getConnection();
        $connection->transaction(function () use ($connection): void {
            $columns = config('totp-login.columns');
            $user = $this->user->newQuery()->whereKey($this->user->getKey())->lockForUpdate()->firstOrFail();

            if ($this->onlyIfExpired && now() < $user->{$columns['code_valid_until']}) {
                return;
            }

            $code = self::createCode();
            $user->{$columns['code']} = Hash::make($code);
            $user->{$columns['code_valid_until']} = now()->addSeconds(config('totp-login.code.expires_in'));
            $user->saveQuietly();
            $this->user->setRawAttributes($user->getAttributes(), true);

            $connection->afterCommit(function () use ($user, $code): void {
                $notification = config('totp-login.notification');
                $user->notify(new $notification($code, $this->ip));
            });
        });
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
