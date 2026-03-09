<?php

namespace PictaStudio\Translatable;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PictaStudio\Translatable\Console\Commands\{InstallCommand, TranslateMissingCommand, TranslateModelsCommand};
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\Middleware\SetLocaleFromHeader;

class TranslatableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeTranslatableConfig();

        $this->app->singleton(Locales::class);
        $this->app->singleton(RouteRequestAuthorizer::class);
        $this->app->alias(Locales::class, 'translatable.locales');
        $this->app->alias(Locales::class, 'translatable');
        $this->app->alias(RouteRequestAuthorizer::class, 'translatable.ai.authorizer');

        $this->commands([
            InstallCommand::class,
            TranslateMissingCommand::class,
            TranslateModelsCommand::class,
        ]);

        $this->registerCommandScheduling();
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

            $this->publishes([
                __DIR__ . '/../bruno/translatable' => base_path('bruno/translatable'),
            ], 'translatable-bruno');
        }

        if (!$this->shouldRegisterLocaleMiddleware()) {
            if ($this->shouldRegisterApiRoutes()) {
                $this->registerApiRoutes();
            }

            return;
        }

        $this->registerLocaleMiddleware();

        if ($this->shouldRegisterApiRoutes()) {
            $this->registerApiRoutes();
        }
    }

    protected function mergeTranslatableConfig(): void
    {
        $packageConfig = require __DIR__ . '/../config/translatable.php';
        $applicationConfig = config('translatable', []);

        config()->set(
            'translatable',
            $this->mergeConfigRecursively(
                $packageConfig,
                is_array($applicationConfig) ? $applicationConfig : []
            )
        );
    }

    /**
     * Merge associative config arrays recursively while preserving list overrides.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function mergeConfigRecursively(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $defaults)
                && is_array($defaults[$key])
                && is_array($value)
                && !array_is_list($defaults[$key])
                && !array_is_list($value)
            ) {
                $defaults[$key] = $this->mergeConfigRecursively($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
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

    protected function shouldRegisterApiRoutes(): bool
    {
        return (bool) config('translatable.routes.api.enable', false);
    }

    protected function registerApiRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware($this->apiRouteMiddleware())
            ->prefix($this->apiRoutePrefix())
            ->name(mb_rtrim($this->apiRouteName(), '.') . '.')
            ->group(__DIR__ . '/../routes/v1/api.php');
    }

    /**
     * @return array<int, string>
     */
    protected function apiRouteMiddleware(): array
    {
        $middleware = config('translatable.routes.api.v1.middleware');

        if (is_array($middleware)) {
            return $middleware;
        }

        return ['api'];
    }

    protected function apiRoutePrefix(): string
    {
        return (string) config('translatable.routes.api.v1.prefix', 'api/translatable/v1');
    }

    protected function apiRouteName(): string
    {
        return (string) config('translatable.routes.api.v1.name', 'api.translatable.v1');
    }

    protected function registerCommandScheduling(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (!(bool) config('translatable.commands.translate_missing.enabled', false)) {
                return;
            }

            $expression = config('translatable.commands.translate_missing.schedule', '0 * * * *');

            if (!is_string($expression) || $expression === '') {
                return;
            }

            $schedule->command('translatable:translate-missing')->cron($expression);
        });
    }
}
