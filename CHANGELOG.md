# Changelog

All notable changes to `translatable` will be documented in this file.

## v0.1.2 - 2026-02-26

**Full Changelog**: https://github.com/pictastudio/translatable/compare/v0.1.1...v0.1.2

## v0.1.1 - 2026-02-26

### What's Changed

- code cleanup

**Full Changelog**: https://github.com/pictastudio/translatable/compare/v0.1.0...v0.1.1

## v0.1.0 - 2026-02-25

### Laravel Translatable - Initial release

Single-table polymorphic translations for Laravel Eloquent models, with logic aligned to the Laravel Translatable approach.

#### Features

- **Translatable trait** - Add `Translatable` to any Eloquent model and declare `$translatedAttributes` for single-table polymorphic translations.
- **Unified translations table** - All translations in one `translations` table with `translatable_type`, `translatable_id`, `locale`, `attribute`, and `value`.
- **Locale-keyed input** - Create/update with `attribute:locale` (e.g. `title:en`, `title:it`) or locale-keyed arrays (`en` => `['title' => '...']`).
- **Base attribute sync** - With `sync_base_attributes` enabled (default), translated values are synced to matching model columns when they exist, so you can keep non-null columns (e.g. `name`) without making them nullable.
- **Locale middleware** - `SetLocaleFromHeader` reads a configurable request header (default: `Locale`) and sets the app locale when the value is in the configured `locales` list.
- **Config & migrations** - Publish via `--tag=translatable-config` and `--tag=translatable-migrations`.

#### Improvements

- Default locale configuration (e.g. default locale `it`).
- Timestamps and related fixes for the translation model.
- Additional methods added for completeness.

#### Maintenance

- Dependabot: bump `dependabot/fetch-metadata` from 2.0.0 to 2.5.0 (GitHub Actions).

**Full Changelog**: https://github.com/pictastudio/translatable/commits/v0.1.0
