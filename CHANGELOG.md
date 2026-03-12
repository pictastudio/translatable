# Changelog

All notable changes to `translatable` will be documented in this file.

## v0.2.1 - 2026-03-12

### What's Changed

#### Fixes

- **Localized updates preserve base columns** - updating a translated attribute on an existing model now only updates the translation record, leaving the underlying table column unchanged unless it still needs to be seeded.

#### Docs and Tooling

- Clarified the `sync_base_attributes` behavior in the README so it matches the localized update flow.

#### Tests

- Added regression coverage to confirm localized updates do not overwrite existing base column values.

**Full Changelog**: https://github.com/pictastudio/translatable/compare/v0.2.0...v0.2.1

## v0.2.0 - 2026-03-09

### What's Changed

This minor release introduces the new AI translation workflow, adds a versioned API surface for translation operations, and expands docs and test coverage.

#### Features

- **AI translation workflow** - added AI-powered translation services, queued translation jobs, accepted/generated metadata, and support for translating all missing values when no request body is provided.
- **Versioned API endpoints** - added `/api/v1` endpoints for locales, translatable models, missing translations, and translation requests.
- **Extensibility improvements** - dispatches a dedicated `AiTranslationsCompleted` event and resolves morph-map aliases when available.

#### Docs and Tooling

- Updated **README** documentation for the new translation flow and package capabilities.
- Added and reorganized **Bruno** requests for locales, models, missing translations, and translate endpoints.

#### Tests

- Added feature tests for **AI translation API**, **queued translations**, **command behavior**, and **locales API** coverage.

**Full Changelog**: https://github.com/pictastudio/translatable/compare/v0.1.3...v0.2.0

## v0.1.3 - 2026-03-05

### What's Changed

#### Features

- **Fallback on original field value** - now if no value is found for translations in the requested or fallback language it falls back to the original field value instead of returning null

**Full Changelog**: https://github.com/pictastudio/translatable/compare/v0.1.2...v0.1.3

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
