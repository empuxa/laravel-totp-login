<?php

use Empuxa\TotpLogin\Tests\TestbenchTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestbenchTestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param  array<int|string,mixed>  $params
 */
function createUser(array $params = []): Model
{
    return config('totp-login.model')::create(array_merge(
        // Default Laravel params
        [
            'name'              => 'Test User',
            'email'             => 'user@example.com',
            'email_verified_at' => now(),
            // Hash for "password"
            'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token'    => Str::random(10),
        ],
        // Default package params
        [
            // Hash for "123456"
            config('totp-login.columns.code')             => '$2y$10$DJDW1ZCcd.6iqtq/JdivDuWTUCDxVES/efzv1e61CKLhdIJPupzI6',
            config('totp-login.columns.code_valid_until') => now()->addSecond(),
        ],
        // Additional test params
        $params,
    ));
}
