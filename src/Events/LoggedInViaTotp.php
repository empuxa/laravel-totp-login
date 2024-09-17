<?php

namespace Empuxa\TotpLogin\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoggedInViaTotp
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public $user, public $request) {}
}
