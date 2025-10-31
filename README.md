# Laravel TOTP Login

[![Latest Version on Packagist](https://img.shields.io/packagist/v/empuxa/laravel-totp-login.svg?style=flat-square)](https://packagist.org/packages/empuxa/laravel-totp-login)
[![Tests](https://img.shields.io/github/actions/workflow/status/empuxa/laravel-totp-login/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/empuxa/laravel-totp-login/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/empuxa/laravel-totp-login.svg?style=flat-square)](https://packagist.org/packages/empuxa/laravel-totp-login)

![Banner](https://banners.beyondco.de/Laravel%20TOTP%20Login.png?theme=light&packageManager=composer+require&packageName=empuxa%2Flaravel-totp-login&pattern=architect&style=style_1&description=Goodbye+passwords%21&md=1&showWatermark=0&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)

Say goodbye to passwords and sign in via a time-based one-time password instead! 
Laravel TOTP Login is a convenient package that allows you to easily add a TOTP login feature to your Laravel application.

## Why Choose Laravel TOTP Login?
You might wonder why you should opt for a TOTP login instead of a magic link solution. 
Well, this package is designed to complement the existing login methods in your application. 
It provides an alternative sign-in option for users who haven't set a password yet or don't have an email address. 
For instance, users who signed up with only a phone number can still enjoy the benefits of secure login through a TOTP.

## Features
- Simplified sign-in process using a TOTP
- Compatibility with existing login methods
- Support for users without passwords or email addresses
- Built-in security protections:
  - Rate limiting with progressive event tracking
  - Timing attack prevention in code validation
  - Race condition prevention with database locking
  - Session fixation protection

![How it works](docs/animation.gif)

## Requirements

In addition to Laravel v9.52 or newer, this package relies on [Alpine.js](https://alpinejs.dev/).
If you're using [Laravel LiveWire](https://laravel-livewire.com/), you are already good to go.
Otherwise, ensure to include Alpine.js in your application.
Also, you need to have a notifiable user model.

## Installation

Install the package via composer:

```bash
composer require empuxa/laravel-totp-login
```

Copy the vendor files and adjust the config file `config/totp-login.php` to your needs:

```bash
php artisan vendor:publish --provider="Empuxa\TotpLogin\TotpLoginServiceProvider"
```

Adjust the config to your needs, then run the migrations:

```bash
php artisan migrate
```

That's it!
You're ready to start using the TOTP login feature in your Laravel application.

## Configuration

The package offers extensive configuration options in `config/totp-login.php`. Key settings include:

- **Rate limiting**: Configure max attempts and throttling behavior
- **Code settings**: Customize code length, expiration time, and validation rules
- **User model**: Specify your user model and identifier column (email, phone, etc.)
- **Notification**: Choose custom notification classes for different channels
- **Events**: Replace default event classes with custom implementations
- **Routes**: Customize route prefix and middleware

See the published [config file](/config/totp-login.php) for detailed explanations of all available options.

## Usage

The sign-in process for this repository involves three steps:
1. Enter the user's email address, phone number, or any other specified identifier, and request a TOTP.
2. If the entered information is valid, a TOTP will be sent to the user. You may need to customize the
notification channel based on the user model you are using.
3. Enter the received TOTP to log in the user.

### Routes

By default, the package registers the following routes under the `/login` prefix (you can change it in 
the config):

- `GET /login` - Show identifier entry form
- `POST /login` - Handle identifier submission and send TOTP
- `GET /login/code` - Show code entry form
- `POST /login/code` - Handle code verification and authenticate user

#### Customizing Routes

You can customize the route prefix in `config/totp-login.php`:

```php
'route' => [
    'prefix' => 'auth', // Changes routes to /auth, /auth/code, etc.
    'middleware' => ['web', 'guest'],
],
```

#### Manual Route Registration

If you need more control, you can disable automatic route registration and register routes manually in 
your `routes/web.php`:

```php
use Empuxa\TotpLogin\Controllers\HandleCodeRequest;
use Empuxa\TotpLogin\Controllers\HandleIdentifierRequest;
use Empuxa\TotpLogin\Controllers\ShowCodeForm;
use Empuxa\TotpLogin\Controllers\ShowIdentifierForm;

Route::prefix('auth')->group(static function (): void {
    Route::get('/login', ShowIdentifierForm::class)->name('totp-login.identifier.form');
    Route::post('/login', HandleIdentifierRequest::class)->name('totp-login.identifier.handle');
    Route::get('/login/code', ShowCodeForm::class)->name('totp-login.code.form');
    Route::post('/login/code', HandleCodeRequest::class)->name('totp-login.code.handle');
});
```

### Using Custom Identifiers

By default, the package uses email addresses as identifiers. 
However, you can use any column from your user model (phone numbers, usernames, etc.):

```php
// config/totp-login.php
'columns' => [
    'identifier' => 'phone', // Use phone number instead of email
],
```

Make sure to update your validation rules accordingly:

```php
'identifier' => [
    'validation' => 'required|string|regex:/^\+[1-9]\d{1,14}$/', // E.164 phone format
],
```

Don't forget to update the notification afterward to send SMS instead of mails! 

### Superpin for Testing

During development and testing, you can enable a "superpin" that works for all users.
While the superpin is always valid, the package still dispatches the notification, so you can use either 
the superpin or the actual code sent to the user for login.

```env
TOTP_LOGIN_SUPERPIN=123456
```

**Important**: Superpins are automatically disabled in production environments and only work in 
environments specified in your config (default: `local`, `testing`). 
You can also specify individual user identifiers that can bypass environment restrictions for staging/demo 
purposes.

See `config/totp-login.php` for more superpin configuration options.

### Customizing the Views

While the initial steps are relatively straightforward, it's now necessary to customize
the views. 
These views have been designed to be as simple as possible (some might even consider them
"ugly") and can be located in the `resources/views/vendor/totp-login` directory.

*Why are they not visually appealing?*
Different applications adopt various layouts and frameworks. 
Since you have the most knowledge about your application, you can change the views to suit
your specific requirements.

### Modifying the Notification

The package publishes a default notification view at `resources/views/vendor/totp-login/notification.blade.php`.
You may want to make adjustments to this notification to align it with your preferences and needs.

#### Different Notification Channels
If you plan on using SMS or similar as your preferred notification channel, you can create a custom 
notification class.
The TOTP and the user's IP address will be passed to the constructor of this class.
Finally, replace the default notification class within the `config/totp-login.php` file with your custom 
notification.

### Custom User Model Scope

By default, the package looks up users without any additional filtering. 
However, you might need to restrict which users can use TOTP login. 
Common use cases include:

- Only allowing users with verified email addresses
- Excluding deleted or suspended accounts
- Filtering by user type or role (e.g., only customers, not administrators)
- Applying multi-tenancy restrictions

To apply a scope to your user model, add the `totpLoginScope()` method to your User model:

```php
public static function totpLoginScope(): Builder
{
    return self::where('email_verified_at', '!=', null)
               ->where('status', 'active');
}
```

For example, if you're using soft deletes and want to exclude trashed users:

```php
public static function totpLoginScope(): Builder
{
    return self::withoutTrashed();
}
```

Or if you have a multi-tenant application:

```php
public static function totpLoginScope(): Builder
{
    return self::where('tenant_id', session('tenant_id'));
}
```

## Events

The package dispatches various events throughout the authentication process,
allowing you to monitor and respond to authentication attempts, failures, and rate limiting violations.

### Success Events
- **`LoginRequestViaTotp`** - Fired when a user successfully requests a TOTP code
- **`LoggedInViaTotp`** - Fired when a user successfully authenticates with a TOTP code

### Failure Events

#### Identifier Phase
- **`InvalidIdentifierFormat`** - Invalid identifier format (e.g., invalid email)
- **`UserNotFound`** - Valid format but user doesn't exist
- **`IdentifierRateLimitExceeded`** - First time hitting identifier rate limit
- **`IdentifierRateLimitContinued`** - Continued attempts after identifier rate limit hit

#### Code Phase
- **`MissingSessionInformation`** - Session expired or missing
- **`MissingCodeData`** - Code data not properly submitted
- **`InvalidCodeFormat`** - Invalid code format or length
- **`CodeExpired`** - Valid code but expired
- **`IncorrectCode`** - Wrong code entered
- **`CodeRateLimitExceeded`** - First time hitting code rate limit
- **`CodeRateLimitContinued`** - Continued attempts after code rate limit hit

### Rate Limit Events
- **`Lockout`** (Laravel's core event) - Fired alongside `*RateLimitExceeded` events to follow Laravel's 
conventions and allow integration with existing Laravel authentication listeners

### Rate Limit Event Behavior

The package distinguishes between initial rate limit violations and persistent abuse:

1. **First rate limit hit**: Fires `CodeRateLimitExceeded` or `IdentifierRateLimitExceeded` 
(package-specific) + `Lockout` (Laravel's standard event for rate limiting)
2. **Subsequent attempts**: Fires `CodeRateLimitContinued` or `IdentifierRateLimitContinued`
on each attempt (no `Lockout` event)

This allows you to:
- Monitor initial rate limit violations
- Detect persistent brute force attacks
- Implement progressive security measures (e.g., IP blocking)

### Listening to Events

#### Using Event Subscriber (Recommended)

The recommended approach is to use an event subscriber with config keys. 
This way, if you customize the event classes in your config, your listeners will automatically use the 
correct events:

```php
namespace App\Listeners;

class TotpLoginEventSubscriber
{
    public function subscribe(): array
    {
        return [
            config('totp-login.events.login_request_via_totp') => [],
            config('totp-login.events.logged_in_via_totp') => [
                LogLoginEvent::class,
            ],
            config('totp-login.events.code_rate_limit_exceeded') => [
                LogRateLimitViolation::class,
            ],
            config('totp-login.events.code_rate_limit_continued') => [
                AlertSecurityTeam::class,
                BlockSuspiciousIP::class,
            ],
            config('totp-login.events.identifier_rate_limit_exceeded') => [
                LogRateLimitViolation::class,
            ],
            config('totp-login.events.identifier_rate_limit_continued') => [
                AlertSecurityTeam::class,
                BlockSuspiciousIP::class,
            ],
        ];
    }
}
```

Register the subscriber in your `EventServiceProvider`:

```php
use App\Listeners\TotpLoginEventSubscriber;

protected $subscribe = [
    TotpLoginEventSubscriber::class,
];
```

#### Using Direct Event Classes

Alternatively, you can listen to events directly in your `EventServiceProvider`:

```php
use Empuxa\TotpLogin\Events\CodeRateLimitExceeded;
use Empuxa\TotpLogin\Events\CodeRateLimitContinued;

protected $listen = [
    CodeRateLimitExceeded::class => [
        LogRateLimitViolation::class,
    ],
    CodeRateLimitContinued::class => [
        AlertSecurityTeam::class,
        BlockSuspiciousIP::class,
    ],
];
```

### Customizing Events

All events are configurable in `config/totp-login.php` under the `events` key. 
You can replace the default event classes with your own custom implementations:

```php
// config/totp-login.php
'events' => [
    'code_rate_limit_exceeded' => \App\Events\CustomCodeRateLimitExceeded::class,
    'code_rate_limit_continued' => \App\Events\CustomCodeRateLimitContinued::class,
    'identifier_rate_limit_exceeded' => \App\Events\CustomIdentifierRateLimitExceeded::class,
    'identifier_rate_limit_continued' => \App\Events\CustomIdentifierRateLimitContinued::class,
    // ... other events
],
```

When using the event subscriber approach with config keys (recommended), your listeners 
will automatically use these custom event classes without any changes to your subscriber.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Marco Raddatz](https://github.com/marcoraddatz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
