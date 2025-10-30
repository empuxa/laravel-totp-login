<?php

namespace Empuxa\TotpLogin\Controllers;

use Empuxa\TotpLogin\Events\LoggedInViaTotp;
use Empuxa\TotpLogin\Jobs\ResetLoginCode;
use Empuxa\TotpLogin\Requests\CodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class HandleCodeRequest extends Controller
{
    protected ?string $code = null;

    /**
     * @var \Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\Authenticatable
     */
    protected $user;

    /**
     * @throws \Throwable
     */
    public function __invoke(CodeRequest $request): RedirectResponse
    {
        // SESSION FIXATION PREVENTION:
        // To prevent session fixation attacks (where an attacker tricks a victim into using
        // a session ID controlled by the attacker, then hijacks the session after the victim
        // logs in), we must regenerate the session ID before logging in the user.
        //
        // However, session regeneration clears all session data, so we need to:
        // 1. Extract identifier from session
        // 2. Validate the code
        // 3. Fetch user with the extracted identifier
        // 4. Regenerate session (creates new session ID)
        // 5. Login user (with clean session)
        $identifier = session(config('totp-login.columns.identifier'));

        $request->authenticate();

        $user = $request->getUserModel($identifier);

        if (is_null($user)) {
            throw new \RuntimeException('User not found after authentication');
        }

        /** @var \Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\Authenticatable $user */
        $this->user = $user;

        $request->session()->regenerate();

        Auth::login($this->user, $request->input('remember') === 'true');

        ResetLoginCode::dispatch($this->user);

        $event = config('totp-login.events.logged_in_via_totp', LoggedInViaTotp::class);
        event(new $event($this->user, $request));

        return redirect()
            ->intended(config('totp-login.redirect'))
            ->with([
                'message' => __('totp-login::controller.handle_code_request.success'),
            ]);
    }
}
