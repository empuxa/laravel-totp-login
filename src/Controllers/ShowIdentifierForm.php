<?php

namespace Empuxa\TotpLogin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class ShowIdentifierForm extends Controller
{
    public function __invoke(): View
    {
        return view('totp-login::identifier');
    }
}
