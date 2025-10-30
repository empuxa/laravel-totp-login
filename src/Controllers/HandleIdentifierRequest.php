<?php

namespace Empuxa\TotpLogin\Controllers;

use Empuxa\TotpLogin\Events\LoginRequestViaTotp;
use Empuxa\TotpLogin\Jobs\CreateAndSendLoginCode;
use Empuxa\TotpLogin\Requests\IdentifierRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class HandleIdentifierRequest extends Controller
{
    /**
     * Handles the first step of TOTP authentication: identifier (email/phone) submission.
     * Validates the identifier, generates a code, sends it to the user, and stores
     * the identifier in session for the second step (code verification).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __invoke(IdentifierRequest $request): RedirectResponse
    {
        $request->authenticate();

        $identifierData = $request->input(config('totp-login.columns.identifier'));

        $user = $request->getUserModel($identifierData);

        CreateAndSendLoginCode::dispatch($user, $request->ip());

        session([
            config('totp-login.columns.identifier') => $identifierData,
        ]);

        $event = config('totp-login.events.login_request_via_totp', LoginRequestViaTotp::class);
        event(new $event($user, $request));

        return redirect(route('totp-login.code.form'));
    }
}
