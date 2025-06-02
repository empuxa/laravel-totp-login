<?php

namespace Empuxa\TotpLogin\Tests;

use Empuxa\TotpLogin\Models\User;
use Empuxa\TotpLogin\TotpLoginServiceProvider;
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
}
