<?php

return [
    /**
     * The model used for login operations.
     * Default: \App\Models\User::class
     */
    'model'        => \App\Models\User::class,

    /**
     * The notification class used to send the login code to the user.
     * Default: \Empuxa\TotpLogin\Notifications\LoginCode::class
     */
    'notification' => \Empuxa\TotpLogin\Notifications\LoginCode::class,

    'columns'      => [
        /**
         * The column representing the primary identifier for the user model.
         * This will be used for user authentication and to send the PIN.
         * Default: 'email'
         */
        'identifier'       => 'email',

        /**
         * The column where the login PIN is stored.
         * Default: 'login_totp_code'
         */
        'code'             => 'login_totp_code',

        /**
         * The column that stores the expiration time of the PIN.
         * Default: 'login_totp_code_valid_until'
         */
        'code_valid_until' => 'login_totp_code_valid_until',
    ],

    'route'        => [
        /**
         * Middleware applied to the login route.
         * Default: ['web', 'guest']
         */
        'middleware' => ['web', 'guest'],

        /**
         * Prefix for the login route.
         * Default: 'login'
         */
        'prefix'     => 'login',
    ],

    'identifier'   => [
        /**
         * The maximum number of attempts allowed per minute for identifying a user.
         * After exceeding this limit, the user is blocked for 60 seconds.
         * Refer to Laravel's RateLimiter for more details.
         * Default: 5
         */
        'max_attempts'      => 5,

        /**
         * Validation rules for the identifier, typically an email.
         * Default: 'required|string|email'
         */
        'validation'        => 'required|string|email',

        /**
         * Whether to enable throttling for the identifier request.
         * This blocks the user for 60 seconds after exceeding `max_attempts` within a minute.
         * Default: true
         */
        'enable_throttling' => true,
    ],

    'code'         => [
        /**
         * Length of the login PIN.
         * Note that longer PINs may affect layout design.
         * Default: 6
         */
        'length'            => 6,

        /**
         * Time in seconds before the PIN expires.
         * This duration is stored in the `login_totp_code_valid_until` column.
         * Default: 600
         */
        'expires_in'        => 600,

        /**
         * Maximum number of PIN entry attempts allowed per minute.
         * After exceeding this limit, the user is blocked for 60 seconds.
         * Refer to Laravel's RateLimiter for more details.
         * Default: 5
         */
        'max_attempts'      => 5,

        /**
         * Validation rules for the PIN input.
         * Default: 'required|array|size:6'
         */
        'validation'        => 'required|array|size:6',

        /**
         * Whether to enable throttling for PIN entry attempts.
         * This blocks the user for 60 seconds after exceeding `max_attempts` within a minute.
         * Default: true
         */
        'enable_throttling' => true,
    ],

    'superpin'     => [
        /**
         * Enables the "superpin" feature.
         *
         * When enabled, users can log in using a predefined PIN regardless of their individual login codes.
         * To enable it, set the `TOTP_LOGIN_SUPERPIN` environment variable to a number matching the defined
         * code length (e.g., '123456' for a 6-digit code).
         *
         * If `TOTP_LOGIN_SUPERPIN` is set to false, the feature is disabled.
         * Default: env('TOTP_LOGIN_SUPERPIN', false)
         */
        'pin'                   => env('TOTP_LOGIN_SUPERPIN', false),

        /**
         * Environments where the superpin feature is allowed.
         * Note: The production environment is never permitted.
         * Default: ['local', 'testing']
         */
        'environments'          => ['local', 'testing'],

        /**
         * Specific user identifiers that can bypass the environment check for the superpin.
         * Useful for testing in production or providing vendor access with test accounts.
         * Default: []
         */
        'bypassing_identifiers' => [],
    ],

    /**
     * The redirect path after a successful login.
     * Default: '/'
     */
    'redirect'     => '/',

    'events'       => [
        /**
         * Triggered when a user requests a TOTP login code.
         * Default: \Empuxa\TotpLogin\Events\LoginRequestViaTotp::class
         */
        'login_request_via_totp' => \Empuxa\TotpLogin\Events\LoginRequestViaTotp::class,

        /**
         * Triggered when a user successfully logs in using TOTP.
         * Default: \Empuxa\TotpLogin\Events\LoggedInViaTotp::class
         */
        'logged_in_via_totp'     => \Empuxa\TotpLogin\Events\LoggedInViaTotp::class,

        /**
         * Triggered when a user is locked out after too many failed attempts.
         * Default: \Illuminate\Auth\Events\Lockout::class
         */
        'lockout'                => \Illuminate\Auth\Events\Lockout::class,
    ],
];
