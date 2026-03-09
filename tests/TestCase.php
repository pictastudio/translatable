<?php

namespace PictaStudio\Translatable\Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Route, Schema};
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\TranslatableServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([], false);
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
        $this->createModelTables();
        $this->registerTestRoutes();
        app(RouteRequestAuthorizer::class)->using(null);
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('ai.default', 'openai');
        config()->set('ai.providers.openai', [
            'driver' => 'openai',
            'key' => 'test-key',
        ]);
        config()->set('translatable.locales', ['en', 'it', 'fr']);
        config()->set('translatable.locale', null);
        config()->set('translatable.fallback_locale', 'en');
        config()->set('translatable.register_locale_middleware', true);
        config()->set('translatable.locale_header', 'Locale');
        config()->set('translatable.routes.api.enable', true);
        config()->set('translatable.routes.api.v1.middleware', []);
        config()->set('translatable.ai.queue.name', 'default');
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
            $table->string('name');
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
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
            AiServiceProvider::class,
            TranslatableServiceProvider::class,
        ];
    }
}
