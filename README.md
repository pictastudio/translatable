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

### API Endpoint

An opt-in API endpoint is available for admin dashboards.

Enable it in `config/translatable.php`:

```php
'ai' => [
    'routes' => [
        'enabled' => true,
        'middleware' => ['api', 'auth:sanctum'],
        'prefix' => 'translatable/ai',
        'name' => 'translatable.ai.',
    ],
],
```

Then call:

```http
POST /translatable/ai/translate
Content-Type: application/json
```

Example payload:

```json
{
    "model": "App\\Models\\Page",
    "id": 1,
    "source_locale": "en",
    "target_locales": ["it", "fr", "de"],
    "attributes": ["title", "content"],
    "force": false
}
```

The response includes the translated values and a summary of how many fields were translated.
