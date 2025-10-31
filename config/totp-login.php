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
         * Prefix for the login routes. This will be the base path for all TOTP login routes.
         * For example, 'login' creates routes at /login and /login/code
         * Change to 'auth' to create routes at /auth and /auth/code
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
        'login_request_via_totp'         => \Empuxa\TotpLogin\Events\LoginRequestViaTotp::class,

        /**
         * Triggered when a user successfully logs in using TOTP.
         * Default: \Empuxa\TotpLogin\Events\LoggedInViaTotp::class
         */
        'logged_in_via_totp'             => \Empuxa\TotpLogin\Events\LoggedInViaTotp::class,

        /**
         * Triggered when a user is locked out after too many failed attempts.
         * Default: \Illuminate\Auth\Events\Lockout::class
         */
        'lockout'                        => \Illuminate\Auth\Events\Lockout::class,

        /**
         * Triggered when identifier validation fails (e.g., invalid email format).
         * Default: \Empuxa\TotpLogin\Events\InvalidIdentifierFormat::class
         */
        'invalid_identifier_format'      => \Empuxa\TotpLogin\Events\InvalidIdentifierFormat::class,

        /**
         * Triggered when the provided identifier doesn't match any user in the database.
         * Default: \Empuxa\TotpLogin\Events\UserNotFound::class
         */
        'user_not_found'                 => \Empuxa\TotpLogin\Events\UserNotFound::class,

        /**
         * Triggered when too many identifier attempts are made and the user is rate limited.
         * Default: \Empuxa\TotpLogin\Events\IdentifierRateLimitExceeded::class
         */
        'identifier_rate_limit_exceeded' => \Empuxa\TotpLogin\Events\IdentifierRateLimitExceeded::class,

        /**
         * Triggered when session information is missing (e.g., session expired).
         * Default: \Empuxa\TotpLogin\Events\MissingSessionInformation::class
         */
        'missing_session_information'    => \Empuxa\TotpLogin\Events\MissingSessionInformation::class,

        /**
         * Triggered when code data is not properly submitted in the request.
         * Default: \Empuxa\TotpLogin\Events\MissingCodeData::class
         */
        'missing_code_data'              => \Empuxa\TotpLogin\Events\MissingCodeData::class,

        /**
         * Triggered when code validation fails (e.g., invalid format or length).
         * Default: \Empuxa\TotpLogin\Events\InvalidCodeFormat::class
         */
        'invalid_code_format'            => \Empuxa\TotpLogin\Events\InvalidCodeFormat::class,

        /**
         * Triggered when the TOTP code has expired.
         * Default: \Empuxa\TotpLogin\Events\CodeExpired::class
         */
        'code_expired'                   => \Empuxa\TotpLogin\Events\CodeExpired::class,

        /**
         * Triggered when an incorrect TOTP code is entered.
         * Default: \Empuxa\TotpLogin\Events\IncorrectCode::class
         */
        'incorrect_code'                 => \Empuxa\TotpLogin\Events\IncorrectCode::class,

        /**
         * Triggered when too many incorrect code attempts are made and the user is rate limited.
         * Default: \Empuxa\TotpLogin\Events\CodeRateLimitExceeded::class
         */
        'code_rate_limit_exceeded'       => \Empuxa\TotpLogin\Events\CodeRateLimitExceeded::class,

        /**
         * Triggered when a user continues to submit requests after hitting the code rate limit.
         * Useful for detecting persistent brute force attempts.
         * Default: \Empuxa\TotpLogin\Events\CodeRateLimitContinued::class
         */
        'code_rate_limit_continued'      => \Empuxa\TotpLogin\Events\CodeRateLimitContinued::class,

        /**
         * Triggered when a user continues to submit requests after hitting the identifier rate limit.
         * Useful for detecting persistent brute force attempts.
         * Default: \Empuxa\TotpLogin\Events\IdentifierRateLimitContinued::class
         */
        'identifier_rate_limit_continued' => \Empuxa\TotpLogin\Events\IdentifierRateLimitContinued::class,
    ],
];
