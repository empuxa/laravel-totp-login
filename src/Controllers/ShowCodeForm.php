<?php

namespace Empuxa\TotpLogin\Controllers;

use Empuxa\TotpLogin\Exceptions\MissingSessionInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class ShowCodeForm extends Controller
{
    /**
     * @throws \Throwable
     */
    public function __invoke(): RedirectResponse|View
    {
        /** @phpstan-ignore method.notFound */
        if (auth()->check()) {
            return redirect()->intended(config('totp-login.redirect'));
        }

        throw_unless(session(config('totp-login.columns.identifier')), MissingSessionInformation::class);

        return view('totp-login::code', [
            config('totp-login.columns.identifier') => session(config('totp-login.columns.identifier')),
        ]);
    }
}
