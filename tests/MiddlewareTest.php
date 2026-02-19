<?php

use function Pest\Laravel\get;

it('sets the app locale from a valid request header', function (): void {
    get('/locale-check', ['Locale' => 'it'])
        ->assertOk()
        ->assertSee('it');
});

it('ignores invalid locale values in the request header', function (): void {
    get('/locale-check', ['Locale' => 'de'])
        ->assertOk()
        ->assertSee('en');
});
