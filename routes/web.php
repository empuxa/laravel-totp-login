<?php

use Empuxa\TotpLogin\Controllers\HandleCodeRequest;
use Empuxa\TotpLogin\Controllers\HandleIdentifierRequest;
use Empuxa\TotpLogin\Controllers\ShowCodeForm;
use Empuxa\TotpLogin\Controllers\ShowIdentifierForm;
use Illuminate\Support\Facades\Route;

Route::middleware(config('totp-login.route.middleware'))
    ->prefix(config('totp-login.route.prefix'))
    ->group(static function (): void {
        Route::get('/', ShowIdentifierForm::class)->name('totp-login.identifier.form');
        Route::post('/', HandleIdentifierRequest::class)->name('totp-login.identifier.handle');

        Route::get('/code', ShowCodeForm::class)->name('totp-login.code.form');
        Route::post('/code', HandleCodeRequest::class)->name('totp-login.code.handle');
    });
