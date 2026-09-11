<?php

namespace Empuxa\TotpLogin\Controllers;

use Empuxa\TotpLogin\Events\LoggedInViaTotp;
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
        $request->authenticate();

        $user = $request->getAuthenticatedUser();

        if (is_null($user)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => __('totp-login::controller.handle_code_request.error.invalid'),
            ]);
        }

        /** @var \Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\Authenticatable $user */
        $this->user = $user;

        $request->session()->regenerate();

        Auth::login($this->user, $request->input('remember') === 'true');

        $event = config('totp-login.events.logged_in_via_totp', LoggedInViaTotp::class);
        event(new $event($this->user, $request));

        return redirect()
            ->intended(config('totp-login.redirect'))
            ->with([
                'message' => __('totp-login::controller.handle_code_request.success'),
            ]);
    }
}
