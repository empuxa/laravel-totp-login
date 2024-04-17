<?php

return [
    /**
     * The model to use for the login.
     * Default: \App\Models\User::class
     */
    'model'        => \App\Models\User::class,

    /**
     * The notification to send to the user.
     * Default: \Empuxa\PinLogin\Notifications\LoginPin::class
     */
    'notification' => \Empuxa\PinLogin\Notifications\LoginPin::class,

    'columns'      => [
        /**
         * The main identifier of the user model.
         * We will use this column to authenticate the user and to send the PIN to.
         * Default: 'email'
         */
        'identifier'      => 'email',

        /**
         * The column where the PIN is stored.
         * Default: 'login_pin'
         */
        'pin'             => 'login_pin',

        /**
         * The column where we store the information, how long the PIN is valid.
         * Default: 'login_pin_valid_until'
         */
        'pin_valid_until' => 'login_pin_valid_until',
    ],

    'route'        => [
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

    'identifier'   => [
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

    'pin'          => [
        /**
         * The length of the PIN.
         * Keep in mind that longer PINs might break the layout.
         * Default: 6
         */
        'length'            => 6,

        /**
         * The time in seconds after which the PIN expires.
         * This is the information being stored in the `login_pin_valid_until` column.
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

    /**
     * Enable the "superpin" feature.
     * When enabled, any user can also sign in with the PIN of your choice on non-production environments.
     * Set the environment variable `PIN_LOGIN_SUPERPIN` to the PIN you want to use.
     * Default: env('PIN_LOGIN_SUPERPIN', false)
     */
    'superpin'     => env('PIN_LOGIN_SUPERPIN', false),

    /**
     * The redirect path after a successful login.
     * Default: '/'
     */
    'redirect'     => '/',

    'events'       => [
        /**
         * This event is fired when a user submits a PIN.
         * Default: \Empuxa\PinLogin\Events\PinRequested::class
         */
        'login_request_via_pin' => \Empuxa\PinLogin\Events\LoginRequestViaPin::class,

        /**
         * This event is fired when a user was successfully logged in.
         * Default: \Empuxa\PinLogin\Events\LoggedInViaPin::class
         */
        'logged_in_via_pin'     => \Empuxa\PinLogin\Events\LoggedInViaPin::class,

        /**
         * This event is fired when a user was successfully logged in.
         * Default: \Illuminate\Auth\Events\Lockout::class
         */
        'lockout'               => \Illuminate\Auth\Events\Lockout::class,
    ],
];
