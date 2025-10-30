<?php

namespace Empuxa\TotpLogin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class ShowIdentifierForm extends Controller
{
    public function __invoke(): RedirectResponse|View
    {
        /** @phpstan-ignore method.notFound */
        if (auth()->check()) {
            return redirect()->intended(config('totp-login.redirect'));
        }

        return view('totp-login::identifier');
    }
}
