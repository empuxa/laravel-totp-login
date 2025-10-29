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
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $user;

    /**
     * @throws \Throwable
     */
    public function __invoke(CodeRequest $request): RedirectResponse
    {
        // Get identifier BEFORE regenerating session to prevent session fixation
        $identifier = session(config('totp-login.columns.identifier'));

        $request->authenticate();

        $this->user = $request->getUserModel($identifier);

        // Regenerate session AFTER retrieving all needed data
        $request->session()->regenerate();

        Auth::login($this->user, $request->input('remember') === 'true' ?? false);

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
