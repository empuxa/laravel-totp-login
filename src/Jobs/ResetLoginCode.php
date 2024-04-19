<?php

namespace Empuxa\TotpLogin\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResetLoginCode
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public $user)
    {
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        $this->user->{config('totp-login.columns.code_valid_until')} = now()->subMinute();
        $this->user->saveQuietly();
    }
}
