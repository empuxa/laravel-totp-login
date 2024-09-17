<?php

return [
    'handle_code_request' => [
        'success' => 'Successfully logged in.',
        'error'   => [
            'rate_limit' => 'Too many wrong requests. Your account is blocked for :seconds seconds.',
            'expired'    => "The code isn't valid any longer. We've sent you a new mail.",
            'wrong_totp' => 'The code is wrong. You have :attempts_left more attempts until we temporarily block your account.',
        ],
    ],
];
