<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | Flat locales: ['en', 'it']
    | Country-based locales: ['en' => ['GB', 'US']]
    |
    */
    'locales' => [
        'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Separator
    |--------------------------------------------------------------------------
    */
    'locale_separator' => '-',

    /*
    |--------------------------------------------------------------------------
    | Forced Locale
    |--------------------------------------------------------------------------
    |
    | If null, the app locale is used.
    |
    */
    'locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | If null, no fixed fallback locale is used.
    |
    */
    'fallback_locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Fallback Behavior
    |--------------------------------------------------------------------------
    */
    'use_fallback' => true,
    'use_property_fallback' => true,

    /*
    |--------------------------------------------------------------------------
    | Translation Storage
    |--------------------------------------------------------------------------
    */
    'translation_model' => \PictaStudio\Translatable\Translation::class,
    'locale_key' => 'locale',
    'translations_wrapper' => null,
    'to_array_always_loads_translations' => true,
    'delete_translations_on_delete' => true,

    /*
    |--------------------------------------------------------------------------
    | Locale Header Middleware
    |--------------------------------------------------------------------------
    |
    | When enabled, the middleware is prepended to the HTTP kernel and
    | sets the application locale from the configured header when valid.
    |
    */
    'register_locale_middleware' => true,
    'locale_header' => 'Locale',
];
