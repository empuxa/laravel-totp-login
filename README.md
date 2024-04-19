# Laravel TOTP Login

[![Latest Version on Packagist](https://img.shields.io/packagist/v/empuxa/laravel-totp-login.svg?style=flat-square)](https://packagist.org/packages/empuxa/laravel-totp-login)
[![Tests](https://img.shields.io/github/actions/workflow/status/empuxa/laravel-totp-login/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/empuxa/laravel-totp-login/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/empuxa/laravel-totp-login.svg?style=flat-square)](https://packagist.org/packages/empuxa/laravel-totp-login)

![Banner](https://banners.beyondco.de/Laravel%20TOTP%20Login.png?theme=light&packageManager=composer+require&packageName=empuxa%2Ftotp-login&pattern=architect&style=style_1&description=Goodbye+passwords%21&md=1&showWatermark=0&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)

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

Run the migrations:

```bash
php artisan migrate
```

That's it!
You're ready to start using the TOTP login feature in your Laravel application.

## Usage

The sign-in process for this repository involves three steps:
1. Enter the user's email address, phone number, or any other specified identifier, and request a TOTP.
2. If the entered information is valid, a TOTP will be sent to the user. You may need to customize the notification channel based on the user model you are using.
3. Enter the received TOTP to log in the user.

### Customizing the Views

While the initial steps are relatively straightforward, it's now necessary to customize the views. 
These views have been designed to be as simple as possible (some might even consider them "ugly") and can be located in the `resources/views/vendor/totp-login` directory.

*Why are they not visually appealing?*
Different applications adopt various layouts and frameworks. 
Since you have the most knowledge about your application, you can change the views to suit your specific requirements.

### Modifying the Notification
Within the copied views, you will come across a notification sent to the user. 
You may want to make adjustments to this notification to align it with your preferences and needs.

#### Different Notification Channels
If you plan on using SMS or similar as your preferred notification channel, you can create a custom notification class.
The TOTP and the user's IP address will be passed to the constructor of this class. 
Finally, replace the default notification class within the `config/totp-login.php` file with your custom notification.

### Custom User Model Scope
To apply a scope to your user model, add the following method to your model:

```php
public static function totpLoginScope(): Builder
{
    return self::yourGlobalScope();
}
```

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
