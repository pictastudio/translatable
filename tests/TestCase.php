<?php

namespace Tests;

use PictaStudio\Translatable\TranslatableServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        $this->withFactories(realpath(__DIR__.'/factories'));
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('translatable.locales', ['el', 'en', 'fr', 'de', 'id', 'en-GB', 'en-US', 'de-DE', 'de-CH']);
    }

    protected function getPackageProviders($app)
    {
        return [
            TranslatableServiceProvider::class,
        ];
    }

    protected function createTables(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vegetables', function (Blueprint $table) {
            $table->increments('identity');
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->morphs('translatable');
            $table->string('locale')->index();
            $table->string('attribute');
            $table->text('value')->nullable();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'attribute'], 'translations_unique');
        });
    }
}
