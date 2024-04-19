<?php

namespace Empuxa\TotpLogin;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TotpLoginServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('totp-login')
            ->hasConfigFile()
            ->hasMigration('add_totp_columns_to_users_table')
            ->hasTranslations()
            ->hasViews()
            ->hasRoute('web');
    }
}
