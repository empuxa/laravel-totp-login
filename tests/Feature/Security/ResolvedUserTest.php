<?php

use Empuxa\TotpLogin\Events\LoggedInViaTotp;
use Empuxa\TotpLogin\Events\LoginRequestViaTotp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('reuses the resolved user without another identifier lookup', function (bool $codePhase) {
    Notification::fake();
    $user = createUser();
    $lookups = [];
    DB::listen(function ($query) use (&$lookups) {
        if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '"email" =')) {
            $lookups[] = $query->sql;
        }
    });
    $eventClass = $codePhase ? LoggedInViaTotp::class : LoginRequestViaTotp::class;
    $checked = false;
    Event::listen($eventClass, function ($event) use (&$checked) {
        expect($event->user)->toBe($event->request->getAuthenticatedUser());
        $checked = true;
    });
    if ($codePhase) {
        $this->withSession(['email' => $user->email])->post(route('totp-login.code.handle'), [
            'code' => str_split('123456'),
        ])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    } else {
        $this->post(route('totp-login.identifier.handle'), ['email' => $user->email])->assertSessionHasNoErrors();
    }
    expect($checked)->toBeTrue();
    expect($lookups)->toHaveCount(1);
})->with([true, false]);
