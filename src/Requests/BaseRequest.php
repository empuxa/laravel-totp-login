<?php

namespace Empuxa\TotpLogin\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guest();
    }

    public function getUserModel(?string $identifier = null, bool $lock = false): ?Model
    {
        $query = config('totp-login.model')::query();

        // If the model has a dedicated scope for the TOTP login, we will use it.
        if (method_exists(config('totp-login.model'), 'totpLoginScope')) {
            $query = config('totp-login.model')::totpLoginScope();
        }

        $query = $query->where(
            config('totp-login.columns.identifier'),
            $identifier ?? $this->input(config('totp-login.columns.identifier')),
        );

        // Apply pessimistic locking when requested (typically during code validation)
        if ($lock) {
            $query = $query->lockForUpdate();
        }

        return $query->first();
    }
}
