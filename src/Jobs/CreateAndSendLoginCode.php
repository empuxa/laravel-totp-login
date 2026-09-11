<?php

namespace Empuxa\TotpLogin\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        // Defer the entire operation when the caller owns a transaction. A rollback
        // must neither send a message nor leave a cache reservation behind.
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->handle());

            return;
        }

        $key = 'totp-login:send:' . hash('sha256', Str::lower(
            (string) $this->user->{config('totp-login.columns.identifier')}
        ));
        $throttled = config('totp-login.identifier.enable_throttling', true) !== false;
        $lock = $throttled ? Cache::lock($key . ':lock', 120) : null;
        if ($lock && ! $lock->get()) {
            return;
        }

        try {
            if ($throttled && Cache::has($key)) {
                return;
            }

            $result = $connection->transaction(function (): ?array {
                $columns = config('totp-login.columns');
                $user = $this->user->newQuery()->whereKey($this->user->getKey())->lockForUpdate()->firstOrFail();

                if ($this->onlyIfExpired && now() < $user->{$columns['code_valid_until']}) {
                    return null;
                }

                $code = self::createCode();
                $user->{$columns['code']} = Hash::make($code);
                $user->{$columns['code_valid_until']} = now()->addSeconds(config('totp-login.code.expires_in'));
                $user->saveQuietly();
                $this->user->setRawAttributes($user->getAttributes(), true);

                return [$user, $code];
            });

            if ($result !== null) {
                [$user, $code] = $result;
                $notification = config('totp-login.notification');
                $user->notify(new $notification($code, $this->ip));
                if ($throttled) {
                    Cache::put($key, true, max(1, (int) config('totp-login.identifier.resend_cooldown', 30)));
                }
            }
        } finally {
            $lock?->release();
        }
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
