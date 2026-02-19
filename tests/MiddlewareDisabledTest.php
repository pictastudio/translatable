<?php

use PictaStudio\Translatable\Tests\MiddlewareDisabledTestCase;

use function Pest\Laravel\get;

uses(MiddlewareDisabledTestCase::class);

it('does not apply the locale header when middleware registration is disabled', function (): void {
    get('/locale-check', ['Locale' => 'it'])
        ->assertOk()
        ->assertSee('en');
});
