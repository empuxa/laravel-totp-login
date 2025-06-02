<?php

namespace Empuxa\TotpLogin\Tests;

use Empuxa\TotpLogin\Models\User;
use Empuxa\TotpLogin\TotpLoginServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase;

class TestbenchTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TotpLoginServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations([
            '--database' => 'testbench',
        ]);

        $this->loadMigrationsFrom([
            '--database' => 'testbench',
            '--path'     => __DIR__ . '/../database/migrations',
            '--realpath' => true,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');

        $app['config']->set('database.connections.testbench', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('totp-login.model', User::class);
    }

    /**
     * @param  array<int|string,mixed>  $params
     */
    protected function createUser(array $params = []): Model
    {
        return config('totp-login.model')::create(array_merge(
            // Default Laravel params
            [
                'name'              => 'Test User',
                'email'             => 'user@example.com',
                'email_verified_at' => now(),
                'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token'    => Str::random(10),
            ],
            // Default package params
            [
                config('totp-login.columns.code')             => '$2y$10$DJDW1ZCcd.6iqtq/JdivDuWTUCDxVES/efzv1e61CKLhdIJPupzI6', // 123456,
                config('totp-login.columns.code_valid_until') => now()->addSecond(),
            ],
            // Additional test params
            $params,
        ));
    }
}
