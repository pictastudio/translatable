<?php

namespace PictaStudio\Translatable;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PictaStudio\Translatable\Console\Commands\{InstallCommand, TranslateModelsCommand};
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
            TranslateModelsCommand::class,
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
            if ($this->shouldRegisterAiRoutes()) {
                $this->registerAiRoutes();
            }

            return;
        }

        $this->registerLocaleMiddleware();

        if ($this->shouldRegisterAiRoutes()) {
            $this->registerAiRoutes();
        }
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

    protected function shouldRegisterAiRoutes(): bool
    {
        return (bool) config('translatable.ai.routes.enabled', false);
    }

    protected function registerAiRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware((array) config('translatable.ai.routes.middleware', ['api']))
            ->prefix((string) config('translatable.ai.routes.prefix', 'translatable/ai'))
            ->name((string) config('translatable.ai.routes.name', 'translatable.ai.'))
            ->group(__DIR__ . '/../routes/translatable.php');
    }
}
