# Laravel Translatable

Single-table polymorphic translations for Laravel Eloquent models, with optional AI-powered translation workflows.

## Features

- One shared `translations` table for every translatable model.
- Locale-aware attribute access like `$post->title` and `$post->{'title:fr'}`.
- Mass assignment via `attribute:locale`, locale-keyed arrays, or an optional wrapper key.
- Fallbacks across requested locale, fallback locale, and base model columns.
- Helpers for reading, cloning, serializing, and deleting translations.
- Optional request middleware that sets the app locale from a header.
- AI translation through the Laravel AI SDK, with batching, queues, events, and API endpoints.
- Auto-discovery of translatable models for commands and API consumers.

## Installation

```bash
composer require pictastudio/translatable
```

Laravel auto-discovers the service provider.

The quickest setup path is:

```bash
php artisan translatable:install
php artisan vendor:publish --provider="Laravel\\Ai\\AiServiceProvider" --tag=ai-config
```

`translatable:install` publishes:

- `config/translatable.php`
- package migrations
- the Bruno collection if you opt in

Manual setup is also supported:

```bash
php artisan vendor:publish --tag=translatable-config
php artisan vendor:publish --tag=translatable-migrations
php artisan vendor:publish --provider="Laravel\\Ai\\AiServiceProvider" --tag=ai-config
php artisan migrate
```

Configure at least one Laravel AI provider in `config/ai.php` or with environment variables such as `OPENAI_API_KEY`.

If you are upgrading from an older package version, publish migrations again before running `php artisan migrate` so new translation metadata columns are added.

## Model Setup

Models must both use the trait and implement the package contract so they can be discovered by commands and API endpoints.

```php
use Illuminate\Database\Eloquent\Model;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;

class Post extends Model implements TranslatableContract
{
    use Translatable;

    public array $translatedAttributes = ['title', 'summary'];

    protected $fillable = [
        'slug',
        'title',
        'summary',
    ];
}
```

All translations live in a single `translations` table with:

- `translatable_type`
- `translatable_id`
- `locale`
- `attribute`
- `value`
- `generated_by`
- `accepted_at`
- timestamps

`generated_by` is set to `user` for user-written translations and `ai` for AI-generated ones. User-written translations are automatically accepted; AI-generated translations are stored with `accepted_at = null`.

## Writing Translations

Use locale suffixes:

```php
$post = Post::create([
    'slug' => 'welcome',
    'title:en' => 'Welcome',
    'title:it' => 'Benvenuto',
    'summary:en' => 'A short intro',
]);
```

Use locale-keyed arrays:

```php
$post = Post::create([
    'slug' => 'roadmap',
    'en' => [
        'title' => 'Roadmap',
        'summary' => 'Where the product is going.',
    ],
    'it' => [
        'title' => 'Tabella di marcia',
        'summary' => 'Dove sta andando il prodotto.',
    ],
]);
```

Use translation bags:

```php
$post->translateOrNew('fr')->title = 'Bienvenue';
$post->translateOrNew('fr')->summary = 'Introduction courte';
$post->save();
```

If you set `translatable.translations_wrapper`, the model also accepts nested payloads under that key.

## Reading Translations

The current app locale is used by default:

```php
app()->setLocale('it');

$post->title;          // Benvenuto
$post->{'title:en'};   // Welcome
```

You can work with translation bags directly:

```php
$post->translate('it');
$post->translateOrDefault('fr');
$post->translateOrNew('de');
$post->translateOrFail('en');
```

Other helper methods:

- `$post->hasTranslation('fr')`
- `$post->getTranslationValue('fr', 'title')`
- `$post->getTranslationsArray()`
- `$post->deleteTranslations()`
- `$post->deleteTranslations(['fr', 'de'])`
- `$post->replicateWithTranslations()`
- `$post->setDefaultLocale('fr')`

`setDefaultLocale()` changes the locale used by that model instance without changing the application locale.

## Fallbacks And Base Columns

Fallback behavior is driven by:

- `fallback_locale`
- `use_fallback`
- `use_property_fallback`

When enabled, attribute reads follow this order:

1. Requested locale.
2. Fallback locale, including language fallback for country-based locales such as `en-US -> en`.
3. Base model column when the translated attribute also exists on the model table.

This means existing schemas such as `products.name` can keep working even before every translation is populated.

With `sync_base_attributes=true`, writing a translated value mirrors it into the matching base column when that column exists and the model is being created, or the base column is still empty. This is useful when legacy columns are non-nullable.

## Serialization And Deletion

By default, `toArray()` includes translated attributes for the current locale. Disable that behavior with `to_array_always_loads_translations=false` if you want translation loading to stay fully explicit.

When `delete_translations_on_delete=true`, deleting a translatable model deletes its translations as well.

## Locales Helper

The package registers `PictaStudio\Translatable\Locales` as both:

- the `translatable.locales` container binding
- the `Translatable` facade alias

It exposes the configured locale list and locale utilities:

```php
use PictaStudio\Translatable\Locales;

$locales = app(Locales::class);

$locales->all();                  // ['en', 'it', 'fr']
$locales->current();              // current locale
$locales->fallback('en-US');      // 'en' when configured
$locales->has('fr');              // true
$locales->getCountryLocale('en', 'US'); // en-US
```

`translatable.locales` supports both flat and country-based configuration:

```php
'locales' => [
    'en' => ['US', 'GB'],
    'it',
],
```

That configuration produces `en`, `en-US`, `en-GB`, and `it`.

## Locale Header Middleware

`PictaStudio\Translatable\Middleware\SetLocaleFromHeader` can be prepended to the HTTP kernel automatically.

When enabled, it reads the configured header and only switches locale if the value exists in `translatable.locales`.

Relevant config:

```php
'register_locale_middleware' => true,
'locale_header' => 'Locale',
```

## AI Translation

The package integrates with the Laravel AI SDK through `PictaStudio\Translatable\Ai\ModelTranslator`.

Programmatic usage:

```php
use PictaStudio\Translatable\Ai\ModelTranslator;

$summary = app(ModelTranslator::class)->translate($post, [
    'source_locale' => 'en',
    'target_locales' => ['it', 'fr'],
    'attributes' => ['title', 'summary'],
    'force' => false,
    'provider' => 'openai',
    'model' => 'gpt-4.1-mini',
]);
```

Behavior:

- missing source values are skipped
- existing target translations are skipped unless `force=true`
- models of the same class are translated in shared AI batches
- translated values are saved with `generated_by=ai` and `accepted_at=null`

### Artisan Commands

Translate one model class:

```bash
php artisan translatable:translate "App\\Models\\Post" \
    --ids=1 \
    --source-locale=en \
    --target-locales=it \
    --target-locales=fr \
    --attributes=title \
    --attributes=summary
```

Command behavior:

- omit the model argument to get an interactive search prompt
- omit `--source-locale` to use the current app locale
- omit `--target-locales` to translate into every configured locale except the source locale
- omit `--attributes` to use every value in `$translatedAttributes`
- add `--force` to overwrite existing translations
- use `--provider` and `--ai-model` to override the Laravel AI defaults

Translate all currently missing translations across every discovered translatable model:

```bash
php artisan translatable:translate-missing \
    --source-locale=en \
    --target-locales=it \
    --target-locales=fr
```

`translatable:translate-missing` only uses accepted source translations by default.

### Queueing

API-triggered translations are queued through `PictaStudio\Translatable\Ai\Jobs\TranslateModelsJob`.

Queue config:

```php
'ai' => [
    'source_locale' => null,
    'provider' => null,
    'model' => null,
    'batch_size' => 25,
    'queue' => [
        'connection' => env('TRANSLATABLE_AI_QUEUE_CONNECTION'),
        'name' => env('TRANSLATABLE_AI_QUEUE_NAME', 'default'),
    ],
],
```

### Completion Event

When queued translations finish, the package dispatches `PictaStudio\Translatable\Events\AiTranslationsCompleted`.

```php
use Illuminate\Support\Facades\Event;
use PictaStudio\Translatable\Events\AiTranslationsCompleted;

Event::listen(AiTranslationsCompleted::class, function (AiTranslationsCompleted $event): void {
    $summary = $event->summary;
    $notifiable = $event->notifiable;

    // Send notifications, update UI state, write logs, trigger webhooks, ...
});
```

## HTTP API

The package registers its API routes by default. Disable them with:

```php
'routes' => [
    'api' => [
        'enable' => false,
    ],
],
```

Default route config:

```php
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
```

Endpoints:

- `GET /api/translatable/v1/locales`
- `GET /api/translatable/v1/models`
- `GET /api/translatable/v1/missing-translations`
- `POST /api/translatable/v1/translate`

### `GET /locales`

Returns configured locales and marks the default locale. This endpoint does not use translation API authorization rules.

### `GET /models`

Returns discoverable translatable models:

- `model`
- `morph_alias`
- `attributes`

Morph aliases come from Laravel's morph map when available. Otherwise the fully qualified class name is returned.

### `GET /missing-translations`

Supported query parameters:

- `model`
- `source_locale`
- `target_locales[]`
- `accepted`
- `per_page`
- `page`

`model` accepts either a fully qualified class name or a morph alias.

Rows are only returned when:

- at least one requested source value exists
- at least one target locale is missing a non-empty translation for that value

Response items contain:

- `model_type`
- `model_class`
- `model_id`
- `source_locale`
- `target_locales`
- `translated_attributes`
- `source_values`
- `missing`
- `missing_count`

`accepted=true` restricts the scan to accepted translation records. `accepted=false` restricts it to non-accepted translation records.

### `POST /translate`

Example payload:

```json
{
    "model": "post",
    "ids": [1, 2],
    "source_locale": "en",
    "target_locales": ["it", "fr"],
    "attributes": ["title", "summary"],
    "force": false,
    "provider": "openai",
    "model_name": "gpt-4.1-mini"
}
```

Notes:

- use `id` for one model or `ids` for many
- `model` accepts a morph alias or class name
- `provider` overrides `translatable.ai.provider`
- `model_name` overrides `translatable.ai.model`
- the response is `202 Accepted` after the queue job is dispatched

## API Authorization

Three authorization modes are supported for `/models`, `/missing-translations`, and `/translate`:

- shared token header
- Laravel Gate ability
- custom closure or invokable authorizer

Config example:

```php
'authorization' => [
    'header' => 'X-Translatable-Token',
    'token' => env('TRANSLATABLE_AI_ROUTE_TOKEN'),
    'ability' => null,
    'using' => null,
],
```

Rules:

- if `using` is set, it becomes the source of truth
- otherwise, if `token` is set, the request must provide the configured header
- otherwise, if `ability` is set, the authenticated user must pass that ability for the target model class
- if nothing is configured, route access is controlled only by the route middleware you assigned

Runtime registration example:

```php
use Illuminate\Http\Request;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;

public function boot(): void
{
    app(RouteRequestAuthorizer::class)->using(
        fn (Request $request, string $modelClass): bool => $request->user()?->can('translate-model', $modelClass) ?? false
    );
}
```

When authorization is configured, `/models` and `/missing-translations` automatically filter out models the current request is not allowed to access.

## Scheduled Missing Translation Runs

The service provider can auto-register a scheduler entry for `translatable:translate-missing`.

```php
'commands' => [
    'translate_missing' => [
        'enabled' => env('TRANSLATABLE_TRANSLATE_MISSING_ENABLED', false),
        'schedule' => env('TRANSLATABLE_TRANSLATE_MISSING_SCHEDULE', '0 * * * *'),
    ],
],
```

When enabled, the package adds this command to Laravel's scheduler with the configured cron expression.

## Configuration Reference

`config/translatable.php` exposes these feature flags and integration points:

- `locales`: supported locales, including country-based definitions
- `locale_separator`: separator used for country-based locales
- `locale`: forces a package-level current locale when set
- `fallback_locale`: fixed fallback locale
- `use_fallback`: enables locale fallback resolution
- `use_property_fallback`: enables attribute-level fallback resolution
- `translation_model`: custom translation model class
- `locale_key`: locale column name on the translation table
- `translations_wrapper`: optional wrapper key for nested translation payloads
- `sync_base_attributes`: mirrors translated values into base columns when possible
- `to_array_always_loads_translations`: controls whether `toArray()` auto-includes translated attributes
- `delete_translations_on_delete`: cascades translation deletion from the parent model
- `register_locale_middleware`: auto-registers the locale header middleware
- `locale_header`: request header read by the middleware
- `ai.source_locale`: default source locale for AI translation
- `ai.provider`: default Laravel AI provider override
- `ai.model`: default Laravel AI model override
- `ai.batch_size`: maximum models per AI batch
- `ai.queue.connection`: queue connection used by translation jobs
- `ai.queue.name`: queue name used by translation jobs
- `routes.api.enable`: enables or disables package API routes
- `routes.api.v1.prefix`: API route prefix
- `routes.api.v1.name`: API route name prefix
- `routes.api.v1.middleware`: middleware stack applied to package routes
- `routes.api.v1.authorization.*`: route authorization settings
- `commands.translate_missing.enabled`: registers the scheduler entry
- `commands.translate_missing.schedule`: cron expression for the scheduler entry

## Bruno Collection

Publish the Bruno collection with:

```bash
php artisan vendor:publish --tag=translatable-bruno
```

Or publish it during setup with:

```bash
php artisan translatable:install
```
