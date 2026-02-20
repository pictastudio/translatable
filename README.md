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
php artisan migrate
```

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
