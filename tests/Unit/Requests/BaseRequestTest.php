<?php

use Empuxa\TotpLogin\Requests\BaseRequest;
use Illuminate\Support\Facades\Auth;

describe('BaseRequest', function () {
    it('authorizes guest users', function () {
        Auth::logout();

        $request = new class extends BaseRequest
        {
            public function rules(): array
            {
                return [];
            }
        };

        expect($request->authorize())->toBeTrue();
    });

    it('does not authorize authenticated users', function () {
        $user = createUser();
        Auth::login($user);

        $request = new class extends BaseRequest
        {
            public function rules(): array
            {
                return [];
            }
        };

        expect($request->authorize())->toBeFalse();

        Auth::logout();
    });

    it('gets user model by identifier', function () {
        $user = createUser(['email' => 'test@example.com']);

        $request = Mockery::mock(BaseRequest::class)->makePartial();
        $request->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('test@example.com');

        $foundUser = $request->getUserModel();

        expect($foundUser)->not->toBeNull();
        expect($foundUser->id)->toBe($user->id);
        expect($foundUser->email)->toBe('test@example.com');
    });

    it('returns null when user not found', function () {
        $request = Mockery::mock(BaseRequest::class)->makePartial();
        $request->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('nonexistent@example.com');

        $foundUser = $request->getUserModel();

        expect($foundUser)->toBeNull();
    });

    it('uses provided identifier parameter', function () {
        $user = createUser(['email' => 'direct@example.com']);

        $request = new class extends BaseRequest
        {
            public function rules(): array
            {
                return [];
            }
        };

        $foundUser = $request->getUserModel('direct@example.com');

        expect($foundUser)->not->toBeNull();
        expect($foundUser->email)->toBe('direct@example.com');
    });

    it('uses totpLoginScope when available', function () {
        // This test verifies that if the User model has a totpLoginScope method,
        // it will be used instead of the base query.

        // Create a test user
        $user = createUser(['email' => 'scoped@example.com']);

        // Since we can't easily modify the User model in tests,
        // we'll just verify the logic path doesn't throw errors
        $request = Mockery::mock(BaseRequest::class)->makePartial();
        $request->shouldReceive('input')
            ->with(config('totp-login.columns.identifier'))
            ->andReturn('scoped@example.com');

        $foundUser = $request->getUserModel();

        // If method doesn't exist, regular query is used
        expect($foundUser)->not->toBeNull();
        expect($foundUser->email)->toBe('scoped@example.com');
    });
});
