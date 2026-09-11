<?php

return [
    'handle_identifier_request' => ['success' => 'Falls dieses Konto sich anmelden kann, wird ein Code gesendet. Bitte prüfe deine Nachrichten.'],
    'handle_code_request' => [
        'success' => 'Login erfolgreich.',
        'error'   => [
            'invalid' => 'Der Code konnte nicht akzeptiert werden. Bitte prüfe ihn oder fordere später einen neuen Code an.',
            'rate_limit' => 'Zu viele falsche Anfragen. Ihr Account wurde für :seconds Sekunden geblockt.',
            'expired'    => 'Der eingegebene Code ist nicht mehr gültig. Wir haben Ihnen einen neuen Code per E-Mail geschickt.',
            'wrong_totp' => 'Der Code ist ungültig. Sie haben noch :attempts_left Versuche bis wir Ihren Account temporär blockieren.',
        ],
    ],
];
