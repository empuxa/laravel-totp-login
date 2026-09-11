<?php

return [
    'handle_identifier_request' => ['success' => 'If this account can sign in, a code will be sent. Please check your messages.'],
    'handle_code_request' => [
        'success' => 'Successfully logged in.',
        'error'   => [
            'invalid' => 'The code could not be accepted. Please check it or request a new code later.',
            'rate_limit' => 'Too many wrong requests. Your account is blocked for :seconds seconds.',
            'expired'    => "The code isn't valid any longer. We've sent you a new mail.",
            'wrong_totp' => 'The code is wrong. You have :attempts_left more attempts until we temporarily block your account.',
        ],
    ],
];
