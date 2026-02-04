<?php

namespace PictaStudio\Translatable;

use PictaStudio\Translatable\Traits\Relationship;
use PictaStudio\Translatable\Traits\Scopes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @property-read Collection|Model[] $translation
 * @property-read Collection|Model[] $translations
 * @property-read string $translationModel
 * @property-read string $localeKey
 * @property-read bool $useTranslationFallback
 * @property string[] $translatedAttributes
 *
 * @mixin Model
 */
trait Translatable
{
    use Relationship, Scopes;

    protected static $autoloadTranslations = null;

    protected static $deleteTranslationsCascade = false;

    protected $defaultLocale;

    public static function bootTranslatable(): void
    {
        static::saved(function (Model $model) {
            /* @var Translatable $model */
            return $model->saveTranslations();
        });

        static::deleting(function (Model $model) {
            /* @var Translatable $model */
            if (self::$deleteTranslationsCascade === true) {
                return $model->deleteTranslations();
            }
        });
    }

    public static function defaultAutoloadTranslations(): void
    {
        self::$autoloadTranslations = null;
    }

    public static function disableAutoloadTranslations(): void
    {
        self::$autoloadTranslations = false;
    }

    public static function enableAutoloadTranslations(): void
    {
        self::$autoloadTranslations = true;
    }

    public static function disableDeleteTranslationsCascade(): void
    {
        self::$deleteTranslationsCascade = false;
    }

    public static function enableDeleteTranslationsCascade(): void
    {
        self::$deleteTranslationsCascade = true;
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (
            (! $this->relationLoaded('translations') && ! $this->toArrayAlwaysLoadsTranslations() && is_null(self::$autoloadTranslations))
            || self::$autoloadTranslations === false
        ) {
            return $attributes;
        }

        $hiddenAttributes = $this->getHidden();

        foreach ($this->translatedAttributes as $field) {
            if (in_array($field, $hiddenAttributes)) {
                continue;
            }

            $attributes[$field] = $this->getAttributeOrFallback(null, $field);
        }

        return $attributes;
    }

    /**
     * @param  string|array|null  $locales  The locales to be deleted
     */
    public function deleteTranslations($locales = null): void
    {
        if ($locales === null) {
            $translations = $this->translations()->get();
        } else {
            $locales = (array) $locales;
            $translations = $this->translations()->whereIn($this->getLocaleKey(), $locales)->get();
        }

        $translations->each->delete();

        // we need to manually "reload" the collection built from the relationship
        // otherwise $this->translations()->get() would NOT be the same as $this->translations
        $this->load('translations');
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $values) {
            if ($this->isWrapperAttribute($key)) {
                $this->fill($values);

                unset($attributes[$key]);

                continue;
            }

            if (
                $this->getLocalesHelper()->has($key)
                && is_array($values)
            ) {
                $this->getTranslationOrNew($key)->fill($values);

                unset($attributes[$key]);

                continue;
            }

            [$attribute, $locale] = $this->getAttributeAndLocale($key);

            if (
                $this->getLocalesHelper()->has($locale)
                && $this->isTranslationAttribute($attribute)
            ) {
                $this->getTranslationOrNew($locale)->fill([$attribute => $values]);

                unset($attributes[$key]);
            }
        }

        return parent::fill($attributes);
    }

    public function getAttribute($key)
    {
        [$attribute, $locale] = $this->getAttributeAndLocale($key);

        if ($this->isTranslationAttribute($attribute)) {
            if ($this->getTranslation($locale) === null) {
                return $this->getAttributeValue($attribute);
            }

            // If the given $attribute has a mutator, we push it to $attributes and then call getAttributeValue
            // on it. This way, we can use Eloquent's checking for Mutation, type casting, and
            // Date fields.
            if ($this->hasGetMutator($attribute)) {
                $this->attributes[$attribute] = $this->getAttributeOrFallback($locale, $attribute);

                return $this->getAttributeValue($attribute);
            }

            return $this->getAttributeOrFallback($locale, $attribute);
        }

        return parent::getAttribute($key);
    }

    public function getDefaultLocale(): ?string
    {
        return $this->defaultLocale;
    }

    /**
     * @internal will change to protected
     */
    public function getLocaleKey(): string
    {
        return $this->localeKey ?: config('translatable.locale_key', 'locale');
    }

    public function getNewTranslation(string $locale): TranslationBag
    {
        return new TranslationBag($this, $locale);
    }

    public function getTranslation(?string $locale = null, ?bool $withFallback = null): ?TranslationBag
    {
        $configFallbackLocale = $this->getFallbackLocale();
        $locale = $locale ?: $this->locale();
        $withFallback = $withFallback === null ? $this->useFallback() : $withFallback;
        $fallbackLocale = $this->getFallbackLocale($locale);

        if ($this->hasTranslation($locale)) {
            return new TranslationBag($this, $locale);
        }

        if ($withFallback && $fallbackLocale) {
            if ($this->hasTranslation($fallbackLocale)) {
                return new TranslationBag($this, $fallbackLocale);
            }

            if (
                is_string($configFallbackLocale)
                && $fallbackLocale !== $configFallbackLocale
                && $this->hasTranslation($configFallbackLocale)
            ) {
                return new TranslationBag($this, $configFallbackLocale);
            }
        }

        if ($withFallback && $configFallbackLocale === null) {
            $configuredLocales = $this->getLocalesHelper()->all();

            foreach ($configuredLocales as $configuredLocale) {
                if (
                    $locale !== $configuredLocale
                    && $fallbackLocale !== $configuredLocale
                    && $this->hasTranslation($configuredLocale)
                ) {
                    return new TranslationBag($this, $configuredLocale);
                }
            }
        }

        return null;
    }

    public function getTranslationOrNew(?string $locale = null): TranslationBag
    {
        $locale = $locale ?: $this->locale();

        return $this->getTranslation($locale, false) ?: $this->getNewTranslation($locale);
    }

    public function getTranslationOrFail(string $locale): TranslationBag
    {
        if (($translation = $this->getTranslation($locale, false)) === null) {
            throw (new ModelNotFoundException)->setModel($this->getTranslationModelName(), $locale);
        }

        return $translation;
    }

    public function getTranslationsArray(): array
    {
        $translations = [];

        $entries = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        foreach ($entries as $translation) {
            $translations[$translation->{$this->getLocaleKey()}][$translation->attribute] = $translation->value;
        }

        return $translations;
    }

    public function hasTranslation(?string $locale = null): bool
    {
        $locale = $locale ?: $this->locale();

        if ($this->relationLoaded('translations')) {
            return $this->translations->contains(function (Model $translation) use ($locale) {
                return $translation->getAttribute($this->getLocaleKey()) == $locale;
            });
        }

        return $this->translations()
            ->where($this->getLocaleKey(), $locale)
            ->exists();
    }

    public function isTranslationAttribute(string $key): bool
    {
        return in_array($key, $this->translatedAttributes);
    }

    public function isWrapperAttribute(string $key): bool
    {
        return $key === config('translatable.translations_wrapper');
    }

    public function replicateWithTranslations(?array $except = null): Model
    {
        $newInstance = $this->replicate($except);

        unset($newInstance->translations);
        $entries = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
        foreach ($entries as $translation) {
            $newTranslation = $translation->replicate();
            $newInstance->translations->add($newTranslation);
        }

        return $newInstance;
    }

    public function setAttribute($key, $value)
    {
        [$attribute, $locale] = $this->getAttributeAndLocale($key);

        if ($this->isTranslationAttribute($attribute)) {
            $this->getTranslationOrNew($locale)->$attribute = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function setDefaultLocale(?string $locale)
    {
        $this->defaultLocale = $locale;

        return $this;
    }

    public function translate(?string $locale = null, bool $withFallback = false): ?TranslationBag
    {
        return $this->getTranslation($locale, $withFallback);
    }

    public function translateOrDefault(?string $locale = null): ?TranslationBag
    {
        return $this->getTranslation($locale, true);
    }

    public function translateOrNew(?string $locale = null): TranslationBag
    {
        return $this->getTranslationOrNew($locale);
    }

    public function translateOrFail(string $locale): TranslationBag
    {
        return $this->getTranslationOrFail($locale);
    }

    protected function getLocalesHelper(): Locales
    {
        return app(Locales::class);
    }

    protected function isEmptyTranslatableAttribute(string $key, $value): bool
    {
        return empty($value);
    }

    protected function isTranslationDirty(Model $translation): bool
    {
        return $translation->isDirty();
    }

    protected function locale(): string
    {
        if ($this->getDefaultLocale()) {
            return $this->getDefaultLocale();
        }

        return $this->getLocalesHelper()->current();
    }

    protected function saveTranslations(): bool
    {
        $saved = true;

        if (! $this->relationLoaded('translations')) {
            return $saved;
        }

        foreach ($this->translations as $translation) {
            if ($saved && $this->isTranslationDirty($translation)) {
                if (! empty($connectionName = $this->getConnectionName())) {
                    $translation->setConnection($connectionName);
                }

                $translation->setAttribute('translatable_type', $this->getMorphClass());
                $translation->setAttribute('translatable_id', $this->getKey());
                $saved = $translation->save();
            }
        }

        return $saved;
    }

    protected function getAttributeAndLocale(string $key): array
    {
        if (Str::contains($key, ':')) {
            return explode(':', $key);
        }

        return [$key, $this->locale()];
    }

    protected function getAttributeOrFallback(?string $locale, string $attribute)
    {
        $locale = $locale ?: $this->locale();
        $value = $this->getTranslationValue($locale, $attribute);

        if ($this->usePropertyFallback() && $this->isEmptyTranslatableAttribute($attribute, $value)) {
            $fallbackLocale = $this->getFallbackLocale();
            if ($fallbackLocale) {
                $value = $this->getTranslationValue($fallbackLocale, $attribute);
            }
        }

        return $value;
    }

    protected function getFallbackLocale(?string $locale = null): ?string
    {
        if ($locale && $this->getLocalesHelper()->isLocaleCountryBased($locale)) {
            if ($fallback = $this->getLocalesHelper()->getLanguageFromCountryBasedLocale($locale)) {
                return $fallback;
            }
        }

        return config('translatable.fallback_locale');
    }

    protected function getTranslationEntry(string $locale, string $attribute): ?Model
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->first(function (Model $translation) use ($locale, $attribute) {
                return $translation->getAttribute($this->getLocaleKey()) == $locale
                    && $translation->attribute === $attribute;
            });
        }

        return $this->translations()
            ->where($this->getLocaleKey(), $locale)
            ->where('attribute', $attribute)
            ->first();
    }

    public function getTranslationValue(string $locale, string $attribute)
    {
        $translation = $this->getTranslationEntry($locale, $attribute);

        return $translation ? $translation->value : null;
    }

    public function setTranslationValue(string $locale, string $attribute, $value): void
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
        if (! $this->relationLoaded('translations')) {
            $this->setRelation('translations', $translations);
        }

        $translation = $translations->first(function (Model $translation) use ($locale, $attribute) {
            return $translation->getAttribute($this->getLocaleKey()) == $locale
                && $translation->attribute === $attribute;
        });

        if (! $translation) {
            $modelName = $this->getTranslationModelName();
            /** @var Model $translation */
            $translation = new $modelName;
            $translation->setAttribute($this->getLocaleKey(), $locale);
            $translation->setAttribute('attribute', $attribute);
            $translations->add($translation);
        }

        $translation->setAttribute('value', $value);
    }

    protected function toArrayAlwaysLoadsTranslations(): bool
    {
        return config('translatable.to_array_always_loads_translations', true);
    }

    protected function useFallback(): bool
    {
        if (isset($this->useTranslationFallback) && is_bool($this->useTranslationFallback)) {
            return $this->useTranslationFallback;
        }

        return (bool) config('translatable.use_fallback');
    }

    protected function usePropertyFallback(): bool
    {
        return $this->useFallback() && config('translatable.use_property_fallback', false);
    }

    public function __isset($key)
    {
        return $this->isTranslationAttribute($key) || parent::__isset($key);
    }
}
