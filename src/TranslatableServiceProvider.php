<?php

namespace PictaStudio\Translatable;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use PictaStudio\Translatable\Console\Commands\InstallCommand;
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

        $this->commands([
            InstallCommand::class,
        ]);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/translatable.php' => config_path('translatable.php'),
            ], 'translatable-config');

            $this->publishesMigrations([
                __DIR__ . '/../database/migrations/create_translations_table.php' => database_path('migrations/' . date('Y_m_d_His_') . 'create_translations_table.php'),
            ], 'translatable-migrations');
        }

        if (!$this->shouldRegisterLocaleMiddleware()) {
            return;
        }

        $this->registerLocaleMiddleware();
    }

    protected function shouldRegisterLocaleMiddleware(): bool
    {
        return (bool) config('translatable.register_locale_middleware', true);
    }

    protected function registerLocaleMiddleware(): void
    {
        if (!$this->app->bound(HttpKernel::class)) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (!method_exists($kernel, 'prependMiddleware')) {
            return;
        }

        if (method_exists($kernel, 'hasMiddleware') && $kernel->hasMiddleware(SetLocaleFromHeader::class)) {
            return;
        }

        $kernel->prependMiddleware(SetLocaleFromHeader::class);
    }
}
