<?php

return [
    'handle_code_request' => [
        'success' => 'Login erfolgreich.',
        'error'   => [
            'rate_limit' => 'Zu viele falsche Anfragen. Ihr Account wurde für :seconds Sekunden geblockt.',
            'expired'    => 'Der eingegebene Code ist nicht mehr gültig. Wir haben Ihnen einen neuen Code per E-Mail geschickt.',
            'wrong_totp' => 'Der Code ist ungültig. Sie haben noch :attempts_left Versuche bis wir Ihren Account temporär blockieren.',
        ],
    ],
];
