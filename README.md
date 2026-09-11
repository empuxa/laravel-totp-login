# Laravel TOTP Login

[![Latest Version on Packagist](https://img.shields.io/packagist/v/empuxa/laravel-totp-login.svg?style=flat-square)](https://packagist.org/packages/empuxa/laravel-totp-login)
[![Tests](https://img.shields.io/github/actions/workflow/status/empuxa/laravel-totp-login/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/empuxa/laravel-totp-login/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/empuxa/laravel-totp-login.svg?style=flat-square)](https://packagist.org/packages/empuxa/laravel-totp-login)

![Banner](https://banners.beyondco.de/Laravel%20TOTP%20Login.png?theme=light&packageManager=composer+require&packageName=empuxa%2Flaravel-totp-login&pattern=architect&style=style_1&description=Goodbye+passwords%21&md=1&showWatermark=0&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)

Laravel TOTP Login provides passwordless login using a randomly generated, short-lived one-time code (OTP), delivered by email by default. It does **not** implement the shared-secret TOTP algorithm from RFC 6238 or authenticator-app codes. The package name, namespaces, routes and configuration keys remain unchanged.

## Why Choose Laravel TOTP Login?
You might wonder why you should opt for an OTP login instead of a magic link solution.
Well, this package is designed to complement the existing login methods in your application.
It provides an alternative sign-in option for users who haven't set a password yet or don't have an email address.
For instance, users who signed up with only a phone number can still enjoy the benefits of secure login through an OTP.

## Features
- Simplified sign-in process using an OTP
- Compatibility with existing login methods
- Support for users without passwords or email addresses
- Built-in security protections:
  - Rate limiting with progressive event tracking
  - Hash verification before code or superpin acceptance
  - Code validation and consumption in one database transaction
  - Session fixation protection

![How it works](docs/animation.gif)

## Requirements

Use PHP 8.2+ and a notifiable, authenticatable Eloquent user model. Laravel 12 requires at least 12.61.1; Laravel 13 requires at least 13.12.0 and its own PHP minimum. The legacy Laravel 9–11 constraints remain for compatibility, but known unpatched framework advisories are a **release blocker for those versions**; see [Security Policy](SECURITY.md).

The standard views include their own local Alpine bundle and compiled Tailwind CSS. Publish the assets during installation; no Node build or separate Alpine installation is required in the consuming application. If you integrate the form into an existing Alpine/Livewire layout, use that layout's Alpine instance and register the `code` component before it starts, rather than loading a second Alpine runtime.

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
php artisan vendor:publish --tag=totp-login-assets --force
```

That's it!
You're ready to start using the OTP login feature in your Laravel application.

## Configuration

The package offers extensive configuration options in `config/totp-login.php`. Key settings include:

- **Rate limiting**: Configure max attempts and throttling behavior
- **Code settings**: Customize code length, expiration time, and validation rules
- **User model**: Specify your user model and identifier column (email, phone, etc.)
- **Notification**: Choose custom notification classes for different channels
- **Events**: Replace default event classes with custom implementations
- **Routes**: Customize route prefix and middleware

See the published [config file](/config/totp-login.php) for detailed explanations of all available options.

### Limits and code validation

| Setting | Default | Behavior |
| --- | --- | --- |
| `identifier.max_attempts` | 5 | Requests per account per 60-second window, including successful and unknown-account requests |
| `identifier.max_attempts_per_ip` | 20 | Requests per IP across accounts per 60-second window |
| `identifier.resend_cooldown` | 30 | Minimum seconds between messages to one account |
| `code.max_attempts` | 5 | Failed code checks before the next attempt is blocked |
| `code.length` | 6 | Number of digits generated and accepted by default |
| `code.expires_in` | 600 | Code lifetime in seconds |
| `code.validation` | `null` | Derive `required|array|size:<length>` at request time; explicit rules override this |

Manual requests and automatic replacement of expired codes share account/IP limits and the send cooldown. A blocked resend does not change the stored code. Setting `identifier.enable_throttling` to `false` disables request limits and the send cooldown; `code.enable_throttling` controls code-attempt blocking separately.

Use a shared cache supporting atomic locks across application instances, and a shared rate-limiter store. Configure trusted proxies correctly so client IPs cannot be spoofed. The send lock expires after 120 seconds; set notification transport timeouts below that lease. Avoid changing the cache prefix between instances.

### Upgrading published configuration and views

- Change the old `code.validation = 'required|array|size:6'` to `null` if validation should follow `code.length`. Existing explicit rules remain authoritative.
- Merge the new IP-limit and cooldown settings into published configuration. Successful requests now count; five configured failed code attempts now means exactly five.
- Republish assets with `php artisan vendor:publish --tag=totp-login-assets --force` after package upgrades. Merge the new local asset references and Alpine component markup into customized published views; do not overwrite those views blindly.
- Identifier submissions return the same confirmation and code-form redirect for known, unknown and limited accounts. Invalid/expired/limited codes share the `handle_code_request.error.invalid` translation. Internal events still distinguish failures. Avoid account-existence validation rules such as `exists:users,email` if you need neutral responses; custom rules and listeners can reintroduce that signal.
- Rate-limit cache keys now have a package namespace and a hashed identifier. Old counters are not migrated and expire naturally.
- `BaseRequest::getAuthenticatedUser(): ?Model` exposes the resolved model. `authenticate(): void` remains unchanged. Validation now consumes a successful code before the controller logs in; custom controllers must not perform a second reset.
- No new database columns are needed. The default notification accepts an expiry date with or without an Eloquent datetime cast.

## Usage

The sign-in process for this repository involves three steps:
1. Enter the user's email address, phone number, or any other specified identifier, and request an OTP.
2. If the entered information is valid, an OTP will be sent to the user. You may need to customize the
notification channel based on the user model you are using.
3. Enter the received OTP to log in the user.

### Routes

By default, the package registers the following routes under the `/login` prefix (you can change it in
the config):

- `GET /login` - Show identifier entry form
- `POST /login` - Handle identifier submission and send OTP
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

The following shows equivalent route definitions for applications that replace the package provider’s route registration. The package does not expose a config switch to disable its automatic routes; avoid registering these alongside the default routes:

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

A configured superpin can replace the individual code for testing:

```env
TOTP_LOGIN_SUPERPIN=123456
```

By default it is disabled. When enabled, it works in the configured environments (`local`, `testing` by default). The environment list never permits `production`, **but `superpin.bypassing_identifiers` bypasses that restriction, including in production**. This behavior is unchanged.

A matching user, non-expired code state and applicable attempt limits are still required. Hash verification runs before accepting the superpin. Normal notification generation follows the same request limits and cooldown.

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
The OTP and the user's IP address will be passed to the constructor of this class.
Finally, replace the default notification class within the `config/totp-login.php` file with your custom
notification.

### Custom User Model Scope

By default, the package looks up users without any additional filtering.
However, you might need to restrict which users can use OTP login.
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
- **`LoginRequestViaTotp`** - Fired for an eligible known-account request; it does not confirm delivery and may also fire when the send cooldown suppresses a message
- **`LoggedInViaTotp`** - Fired when a user successfully authenticates with an OTP code

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
- **`CodeExpired`** - Stored code state has expired, regardless of the submitted digits
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

## Authentication guarantees and boundaries

The configured model's connection wraps code lookup, validation and consumption in one transaction. A successful code is expired before the transaction releases its row lock. The controller reuses the validated user. Session regeneration rotates the session ID and CSRF token; it does not erase unrelated session data.

Code creation and reset are synchronous jobs, not queued jobs. Creation invoked inside an existing transaction is deferred until that transaction commits. Code storage commits before notification delivery; a failed delivery releases the send reservation so a later request can try again. Custom notifications may introduce their own queue semantics.

Neutral responses remove the direct account-existence signal, but are not a constant-time guarantee. Synchronous notification delivery, generating a dummy hash for missing codes, expiry handling and custom listeners can have different durations. Use the internal events for monitoring without exposing those distinctions to clients.

## Testing and asset development

```bash
composer test
composer audit --locked
npm ci
npm run build
npx playwright install chromium
npm run test:browser
npm audit
```

Commit changes to the asset sources, `package-lock.json` and rebuilt `resources/dist` together. Bundled third-party licenses are in that directory; review them when upgrading Alpine or its Vue dependencies. CI rebuilds assets and rejects uncommitted output differences.

PHP tests use SQLite, including regressions for code consumption, persisted replacement codes, limits and session behavior. The hash-path tests check real cryptographic verification, not a timing threshold. Browser tests load rendered Blade fixtures with local assets and check the forms at desktop/mobile sizes. They do not deliver real email.

**Real concurrent MySQL/PostgreSQL tests are deferred.** Sequential SQLite tests do not demonstrate production-database locking under concurrent requests. No MySQL/PostgreSQL services or test jobs are included.

CI tests both preferred-current and lowest securely resolvable dependencies. The library's Composer lockfile remains untracked; consuming applications must update and audit their own lockfiles. Do not disable Composer security blocking to force an unsupported legacy framework installation.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Marco Raddatz](https://github.com/marcoraddatz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
