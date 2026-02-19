<?php

namespace PictaStudio\Translatable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Route, Schema};
use Orchestra\Testbench\TestCase as Orchestra;
use PictaStudio\Translatable\TranslatableServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
        $this->createModelTables();
        $this->registerTestRoutes();
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('translatable.locales', ['en', 'it', 'fr']);
        config()->set('translatable.locale', null);
        config()->set('translatable.fallback_locale', 'en');
        config()->set('translatable.register_locale_middleware', true);
        config()->set('translatable.locale_header', 'Locale');
    }

    protected function createModelTables(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
        });
    }

    protected function registerTestRoutes(): void
    {
        Route::get('/locale-check', static fn () => app()->getLocale());
    }

    protected function getPackageProviders($app): array
    {
        return [
            TranslatableServiceProvider::class,
        ];
    }
}
