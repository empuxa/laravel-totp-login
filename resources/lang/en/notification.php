<?php

return [
    'mail' => [
        'subject'    => 'Your login code for :app',
        'greeting'   => 'Hello :name,',
        'line-1'     => 'There was a login request from :ip. Here is your code, which is valid until :valid_until:',
        'line-2'     => "If it wasn't you who requested this, you can ignore this email.",
        'line-3'     => "Thank you for using our services. If you have any questions, feel free to contact us!",
        'cta'        => 'Sign in now',
        'salutation' => 'Best regards,<br>:app-Team',
    ],
];
