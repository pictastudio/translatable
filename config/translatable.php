<?php

use PictaStudio\Translatable\Translation;

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
        'it',
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
    'fallback_locale' => 'it',

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
    'translation_model' => Translation::class,
    'locale_key' => 'locale',
    'translations_wrapper' => null,
    'sync_base_attributes' => true,
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

    /*
    |--------------------------------------------------------------------------
    | AI Translation
    |--------------------------------------------------------------------------
    |
    | These options power model translation through the Laravel AI SDK.
    | Routes are disabled by default so applications can opt in and attach
    | their own authentication / authorization middleware.
    |
    */
    'ai' => [
        'source_locale' => null,
        'provider' => null,
        'model' => null,
        'batch_size' => 25,
        /*
        |--------------------------------------------------------------------------
        | Legacy Route Configuration
        |--------------------------------------------------------------------------
        |
        | Deprecated in favor of translatable.routes.api.* and kept to avoid
        | breaking existing host applications.
        |
        */
        'routes' => [
            'enabled' => false,
            'prefix' => 'api/translatable/v1',
            'name' => 'api.translatable.v1',
            'middleware' => ['api'],
            'authorization' => [
                'header' => 'X-Translatable-Token',
                'token' => env('TRANSLATABLE_AI_ROUTE_TOKEN'),
                'ability' => null,
                'using' => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'api' => [
            'enable' => true,
            'v1' => [
                'prefix' => 'api/translatable/v1',
                'name' => 'api.translatable.v1',
                'middleware' => ['api'],
                'authorization' => [
                    'header' => 'X-Translatable-Token',
                    'token' => env('TRANSLATABLE_AI_ROUTE_TOKEN'),
                    'ability' => null,
                    'using' => null,
                ],
            ],
        ],
    ],
];
