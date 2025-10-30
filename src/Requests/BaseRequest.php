<?php

namespace Empuxa\TotpLogin\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @phpstan-ignore method.notFound */
        return auth()->guest();
    }

    public function getUserModel(?string $identifier = null, bool $lock = false): ?Model
    {
        $query = config('totp-login.model')::query();

        // Optional: Define a totpLoginScope() method on your User model to filter which users
        // can use TOTP login (e.g., only active users, specific roles, etc.).
        // Example: public static function totpLoginScope() { return static::where('active', true); }
        if (method_exists(config('totp-login.model'), 'totpLoginScope')) {
            $query = config('totp-login.model')::totpLoginScope();
        }

        $query = $query->where(
            config('totp-login.columns.identifier'),
            $identifier ?? $this->input(config('totp-login.columns.identifier')),
        );

        // Apply pessimistic locking when requested (typically during code validation).
        // Use $lock=true when you need to prevent race conditions - this ensures only ONE
        // database transaction can read/modify this user record at a time.
        // IMPORTANT: Only use during code validation, NOT during identifier lookup.
        if ($lock) {
            $query = $query->lockForUpdate();
        }

        return $query->first();
    }
}
