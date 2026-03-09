# Laravel Translatable

Single-table polymorphic translations for Laravel Eloquent models.

## Installation

```bash
composer require pictastudio/translatable
```

Laravel auto-discovers the package service provider, so no manual provider registration is required.

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=translatable-config
php artisan vendor:publish --tag=translatable-migrations
php artisan vendor:publish --provider="Laravel\\Ai\\AiServiceProvider" --tag=ai-config
php artisan migrate
```

Configure at least one Laravel AI provider in `config/ai.php` or via environment variables such as `OPENAI_API_KEY`.

## Core Concept

All translations are stored in one table (`translations`) with a polymorphic relation:

- `translatable_type`
- `translatable_id`
- `locale`
- `attribute`
- `value`
- `generated_by` (`user` or `ai`)
- `accepted_at`

This allows translating any model using the same structure.

## Usage

Add the trait and declare translated attributes:

```php
use Illuminate\Database\Eloquent\Model;
use PictaStudio\Translatable\Translatable;

class Post extends Model
{
    use Translatable;

    public array $translatedAttributes = ['title', 'summary'];

    protected $fillable = ['slug', 'title', 'summary'];
}
```

Store translations:

```php
$post = Post::create([
    'slug' => 'welcome',
    'title:en' => 'Welcome',
    'title:it' => 'Benvenuto',
]);
```

Read translations:

```php
app()->setLocale('it');

$post->title;          // Benvenuto (current locale)
$post->{'title:en'};   // Welcome
```

Locale keyed mass assignment is also supported:

```php
Post::create([
    'slug' => 'roadmap',
    'en' => ['title' => 'Roadmap'],
    'it' => ['title' => 'Tabella di marcia'],
]);
```

## Locale Header Middleware

The package includes `PictaStudio\Translatable\Middleware\SetLocaleFromHeader`.

When enabled, it reads a request header (default: `Locale`) and sets `app()->setLocale(...)` only if the value is valid according to configured locales.

Config keys:

- `register_locale_middleware` (default: `true`)
- `locale_header` (default: `Locale`)
- `locales` (valid locale list)

## Configuration

See `/config/translatable.php` for:

- locales and locale separator
- fallback behavior
- translation model and locale key
- base-column syncing for translated attributes (`sync_base_attributes`)
- middleware registration toggle and header name

With `sync_base_attributes=true` (default), translated values are also synced to matching model columns when they exist.
This allows keeping non-null translated columns in your tables (for example `name`) without requiring nullable schema changes.

## AI Translation

The package can translate translatable model attributes with the Laravel AI SDK.
When multiple models are translated in one command or API request, the package batches them into shared AI calls to reduce cost and latency.
API-triggered translations are queued by default on the configured application queue so long-running AI calls do not block the request cycle.

### Artisan Command

Translate one or more models from the terminal:

```bash
php artisan translatable:translate "App\\Models\\Page" \
    --ids=1 \
    --source-locale=en \
    --target-locales=it \
    --target-locales=fr \
    --attributes=title \
    --attributes=content
```

Useful options:

- omitting the model argument opens an interactive `select` prompt listing discovered translatable models
- omitting `--target-locales`, `--attributes`, or `--force` prompts for them interactively
- `--ids=*` limits translation to specific model keys
- omitting `--source-locale` uses the app's current configured locale
- `--target-locales=*` defaults to all configured locales except the source locale
- `--attributes=*` defaults to all model `translatedAttributes`
- `--force` overwrites existing translations
- `--provider=` and `--ai-model=` override the Laravel AI SDK defaults

Translate all currently missing translations across all translatable models:

```bash
php artisan translatable:translate-missing \
    --source-locale=en \
    --target-locales=it \
    --target-locales=fr
```

The dedicated command scans all registered translatable models, finds still-missing translations, and generates only the missing pairs.
By default it uses accepted source translations only.

### API Endpoint

An opt-in API endpoint is available for admin dashboards.

Enable it in `config/translatable.php`:

```php
'routes' => [
    'api' => [
        'enable' => true,
        'v1' => [
            'middleware' => ['api', 'auth:sanctum'],
            'prefix' => 'api/translatable/v1',
            'name' => 'api.translatable.v1',
        ],
    ],
],
```

Then call:

```http
GET /api/translatable/v1/locales
GET /api/translatable/v1/models
GET /api/translatable/v1/missing-translations
POST /api/translatable/v1/translate
Content-Type: application/json
```

The locales endpoint returns the configured locales and indicates which one is the default locale. It is public and does not use the translation API authorization rules.

The discovery endpoint returns the available translatable models and every translated attribute on each one.
Each item includes:

- `model`: fully qualified model class name
- `morph_alias`: registered morph alias when available, otherwise the class name
- `attributes`: translated field list

Use the same authentication and authorization rules as the translate endpoint.

```http
GET /api/translatable/v1/missing-translations
GET /api/translatable/v1/missing-translations?model=page&per_page=50&page=2
```

The missing translations endpoint returns paginated model rows that still have at least one actionable missing translation.
All query parameters are optional.
When omitted, the endpoint:

- scans all discoverable translatable models
- uses the current app locale as the source locale
- checks every other configured locale as a target locale

You can also pass `accepted=true` or `accepted=false` to filter translation-backed source/target entries by whether `accepted_at` is filled.

An item is included only when:

- at least one requested attribute has a non-empty source value
- at least one requested target locale is missing a non-empty translation for that attribute

Each item includes:

- `model_type`: morph alias when available, otherwise the fully qualified model class name
- `model_class`: fully qualified model class name
- `model_id`: model primary key
- `source_locale`: the source locale used for the row
- `target_locales`: all checked target locales
- `translated_attributes`: translated attributes defined on that model
- `source_values`: source-locale values that can be translated
- `missing`: target locale to missing attribute list map
- `missing_count`: total missing locale/attribute pairs for that row

```http
POST /api/translatable/v1/translate
Content-Type: application/json
```

Example payload:

```json
{
    "model": "page",
    "id": 1,
    "source_locale": "en",
    "target_locales": ["it", "fr", "de"],
    "attributes": ["title", "content"],
    "force": false
}
```

The `model` field accepts either the fully qualified class name or a registered morph alias such as `page`.

The response is asynchronous and returns `202 Accepted` after the translation job is queued.
The queued job performs the AI translation work and dispatches an event when it completes.

Queue behavior is configured in `config/translatable.php`:

```php
'ai' => [
    'queue' => [
        'connection' => null,
        'name' => 'default',
    ],
],

'commands' => [
    'translate_missing' => [
        'enabled' => false,
        'schedule' => '0 * * * *',
    ],
],
```

Notes:

- set `ai.queue.name` to choose which queue handles translation jobs; it defaults to `default`
- set `commands.translate_missing.enabled` to `true` to auto-register the scheduled command
- set `commands.translate_missing.schedule` to the cron expression you want Laravel's scheduler to use

Listen for `PictaStudio\Translatable\Events\AiTranslationsCompleted` in your application if you want to react after the job finishes:

```php
use Illuminate\Support\Facades\Event;
use PictaStudio\Translatable\Events\AiTranslationsCompleted;

Event::listen(AiTranslationsCompleted::class, function (AiTranslationsCompleted $event): void {
    $summary = $event->summary;
    $user = $event->notifiable;

    // Send a notification, trigger a webhook, write logs, etc.
});
```

### API Authorization

The endpoint can be protected with custom host-application logic, a shared header token, or an application ability check:

```php
'routes' => [
    'api' => [
        'v1' => [
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

If `using` is set, it should be an invokable class name resolved from the container and it becomes the authorization source of truth.
If `token` is set, requests must send the configured header.
If `ability` is set, the authenticated user must be allowed to perform that ability for the target model class.

For runtime registration, the host application can provide a closure in a service provider:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;

public function boot(): void
{
    app(RouteRequestAuthorizer::class)->using(
        fn (Request $request, string $modelClass): bool => Gate::forUser($request->user())
            ->allows('translate-model', $modelClass)
    );
}
```

Legacy `translatable.ai.routes.*` configuration is still supported as a fallback, but the versioned `translatable.routes.api.v1.*` structure is now the default.

## Bruno Collection

Publish the Bruno collection with:

```bash
php artisan vendor:publish --tag=translatable-bruno
```

Or during package setup:

```bash
php artisan translatable:install
```
