<?php

return [
    /**
     * The model to use for the login.
     * Default: \App\Models\User::class
     */
    'model'        => \App\Models\User::class,

    /**
     * The notification to send to the user.
     * Default: \Empuxa\TotpLogin\Notifications\LoginCode::class
     */
    'notification' => \Empuxa\TotpLogin\Notifications\LoginCode::class,

    'columns' => [
        /**
         * The main identifier of the user model.
         * We will use this column to authenticate the user and to send the PIN to.
         * Default: 'email'
         */
        'identifier'       => 'email',

        /**
         * The column where the PIN is stored.
         * Default: 'login_totp_code'
         */
        'code'             => 'login_totp_code',

        /**
         * The column where we store the information, how long the PIN is valid.
         * Default: 'login_totp_code_valid_until'
         */
        'code_valid_until' => 'login_totp_code_valid_until',
    ],

    'route' => [
        /**
         * The middleware to use for the route.
         * Default: ['web', 'guest']
         */
        'middleware' => ['web', 'guest'],

        /**
         * The prefix for the route.
         * Default: 'login'
         */
        'prefix'     => 'login',
    ],

    'identifier' => [
        /**
         * The maximum number of attempts to get the user per minute.
         * Afterward, the user gets blocked for 60 seconds.
         * See the default Laravel RateLimiter for more information.
         * Default: 5
         */
        'max_attempts'      => 5,

        /**
         * The validation rules for the email.
         * Default: 'required|string|email'
         */
        'validation'        => 'required|string|email',

        /**
         * Enable throttling for the identifier request.
         * This will block the user for 60 seconds after `max_attempts` attempts per minute.
         * Default: true
         */
        'enable_throttling' => true,
    ],

    'code' => [
        /**
         * The length of the PIN.
         * Keep in mind that longer PINs might break the layout.
         * Default: 6
         */
        'length'            => 6,

        /**
         * The time in seconds after which the PIN expires.
         * This is the information being stored in the `login_totp_code_valid_until` column.
         * Default: 600
         */
        'expires_in'        => 600,

        /**
         * The maximum number of attempts to enter a PIN per minute.
         * Afterward, the user gets blocked for 60 seconds.
         * See the default Laravel RateLimiter for more information.
         * Default: 5
         */
        'max_attempts'      => 5,

        /**
         * The validation rules for the PIN array.
         * Default: 'required|array|size:6'
         */
        'validation'        => 'required|array|size:6',

        /**
         * Enable throttling for the PIN request.
         * This will block the user for 60 seconds after `max_attempts` attempts per minute.
         * Default: true
         */
        'enable_throttling' => true,
    ],

    'superpin' => [
        /**
         * Enable the "superpin" feature.
         * When enabled, any user can also sign in with the PIN of your choice.
         * Set the environment variable `TOTP_LOGIN_SUPERPIN` to the PIN you want to use.
         * Default: env('TOTP_LOGIN_SUPERPIN', false)
         */
        'pin'                   => env('TOTP_LOGIN_SUPERPIN', false),

        /**
         * The environments where the superpin is allowed.
         * This is an extra security layer to prevent the superpin from being used in production.
         * Default: ['local', 'testing']
         */
        'environments'          => ['local', 'testing'],

        /**
         * The identifiers that can bypass the environment check.
         * This is useful for testing the superpin in production or providing test accounts to vendors.
         * Default: []
         */
        'bypassing_identifiers' => [],
    ],

    /**
     * The redirect path after a successful login.
     * Default: '/'
     */
    'redirect' => '/',

    'events' => [
        /**
         * This event is fired when a user submits a TOTP.
         * Default: \Empuxa\TotpLogin\Events\PinRequested::class
         */
        'login_request_via_totp' => \Empuxa\TotpLogin\Events\LoginRequestViaTotp::class,

        /**
         * This event is fired when a user was successfully logged in.
         * Default: \Empuxa\TotpLogin\Events\LoggedInViaTotp::class
         */
        'logged_in_via_totp'     => \Empuxa\TotpLogin\Events\LoggedInViaTotp::class,

        /**
         * This event is fired when a user was successfully logged in.
         * Default: \Illuminate\Auth\Events\Lockout::class
         */
        'lockout'                => \Illuminate\Auth\Events\Lockout::class,
    ],
];
