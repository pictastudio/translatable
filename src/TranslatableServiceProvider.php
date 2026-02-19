<?php

namespace PictaStudio\Translatable;

use Illuminate\Foundation\Application;
use Spatie\LaravelPackageTools\{Package, PackageServiceProvider};

class TranslatableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('translatable')
            ->hasConfigFile()
            ->hasMigrations([]);
        // ->hasRoute('api');
    }

    public function registeringPackage()
    {
        $this->app->bind('translatable', fn (Application $app) => (
            $app->make(Translatable::class)
        ));
    }

    public function packageBooted(): void
    {
        // 
    }
}
