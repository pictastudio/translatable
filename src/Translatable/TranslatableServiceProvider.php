<?php

namespace PictaStudio\Translatable;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use PictaStudio\Translatable\Validation\Rules\TranslatableExists;
use PictaStudio\Translatable\Validation\Rules\TranslatableUnique;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;
use PictaStudio\Translatable\Middleware\SetLocaleFromHeader;

class TranslatableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->bound(HttpKernel::class)) {
            /** @var Kernel $httpKernel */
            $httpKernel = $this->app->make(HttpKernel::class);
            $httpKernel->prependMiddleware(SetLocaleFromHeader::class);
        }

        $this->publishes([
            __DIR__.'/../config/translatable.php' => config_path('translatable.php'),
        ], 'translatable');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'translatable-migrations');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'translatable');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/translatable'),
        ], 'translatable-lang');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/translatable.php',
            'translatable'
        );

        Rule::macro('translatableUnique', function (string $model, string $field): TranslatableUnique {
            return new TranslatableUnique($model, $field);
        });
        Rule::macro('translatableExists', function (string $model, string $field): TranslatableExists {
            return new TranslatableExists($model, $field);
        });

        $this->registerTranslatableHelper();
    }

    protected function registerTranslatableHelper(): void
    {
        $this->app->singleton('translatable.locales', Locales::class);
        $this->app->singleton(Locales::class);
    }
}
