<?php

use function Pest\Laravel\getJson;

it('lists available locales and marks the default locale', function (): void {
    config()->set('translatable.locales', ['en', 'it', 'fr']);
    config()->set('translatable.locale', null);
    app('translatable.locales')->load();
    app()->setLocale('en');

    getJson('/api/translatable/v1/locales')
        ->assertOk()
        ->assertJsonPath('meta.count', 3)
        ->assertJsonPath('meta.default_locale', 'en')
        ->assertJsonFragment([
            'code' => 'en',
            'is_default' => true,
        ])
        ->assertJsonFragment([
            'code' => 'it',
            'is_default' => false,
        ])
        ->assertJsonFragment([
            'code' => 'fr',
            'is_default' => false,
        ]);
});

it('does not require translation api authorization to list locales', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');

    getJson('/api/translatable/v1/locales')
        ->assertOk()
        ->assertJsonPath('meta.default_locale', 'en');
});
