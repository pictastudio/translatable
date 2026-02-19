<?php

namespace PictaStudio\Translatable;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use PictaStudio\Translatable\Middleware\SetLocaleFromHeader;

class TranslatableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/translatable.php',
            'translatable'
        );

        $this->app->singleton(Locales::class);
        $this->app->alias(Locales::class, 'translatable.locales');
        $this->app->alias(Locales::class, 'translatable');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/translatable.php' => config_path('translatable.php'),
        ], 'translatable-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'translatable-migrations');

        if (!$this->shouldRegisterLocaleMiddleware()) {
            return;
        }

        if (!$this->app->bound(HttpKernel::class)) {
            return;
        }

        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);
        $kernel->prependMiddleware(SetLocaleFromHeader::class);
    }

    protected function shouldRegisterLocaleMiddleware(): bool
    {
        return (bool) config('translatable.register_locale_middleware', true);
    }
}
