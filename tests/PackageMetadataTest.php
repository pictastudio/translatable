<?php

use PictaStudio\Translatable\Facades\Translatable as TranslatableFacade;
use PictaStudio\Translatable\TranslatableServiceProvider;

it('is auto discoverable by Laravel', function (): void {
    $composer = json_decode(
        (string) file_get_contents(__DIR__ . '/../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    $providers = $composer['extra']['laravel']['providers'] ?? [];
    $aliases = $composer['extra']['laravel']['aliases'] ?? [];

    expect($providers)->toContain(TranslatableServiceProvider::class);
    expect($aliases)->toHaveKey('Translatable', TranslatableFacade::class);
});
