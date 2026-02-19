<?php

namespace PictaStudio\Translatable;

use Illuminate\Database\Eloquent\{Collection, Model, ModelNotFoundException};
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * @property-read Collection<int, Model> $translation
 * @property-read Collection<int, Model> $translations
 * @property array<int, string> $translatedAttributes
 *
 * @mixin Model
 */
trait Translatable
{
    protected ?string $defaultLocale = null;

    public function __isset($key): bool
    {
        if (is_string($key) && $this->isTranslationAttribute($key)) {
            return true;
        }

        return parent::__isset($key);
    }

    public static function bootTranslatable(): void
    {
        static::saved(function (Model $model): bool {
            /** @var self $model */
            return $model->saveTranslations();
        });

        static::deleting(function (Model $model): void {
            /** @var self $model */
            if ((bool) config('translatable.delete_translations_on_delete', true)) {
                $model->deleteTranslations();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $values) {
            if ($this->isWrapperAttribute($key) && is_array($values)) {
                $this->fill($values);
                unset($attributes[$key]);

                continue;
            }

            if ($this->getLocalesHelper()->has($key) && is_array($values)) {
                $this->getTranslationOrNew($key)->fill($values);
                unset($attributes[$key]);

                continue;
            }

            [$attribute, $locale] = $this->getAttributeAndLocale($key);

            if ($this->getLocalesHelper()->has($locale) && $this->isTranslationAttribute($attribute)) {
                $this->getTranslationOrNew($locale)->fill([$attribute => $values]);
                unset($attributes[$key]);
            }
        }

        return parent::fill($attributes);
    }

    public function getAttribute($key): mixed
    {
        if (!is_string($key)) {
            return parent::getAttribute($key);
        }

        [$attribute, $locale] = $this->getAttributeAndLocale($key);

        if (!$this->isTranslationAttribute($attribute)) {
            return parent::getAttribute($key);
        }

        return $this->getAttributeOrFallback($locale, $attribute);
    }

    public function setAttribute($key, $value)
    {
        if (!is_string($key)) {
            return parent::setAttribute($key, $value);
        }

        [$attribute, $locale] = $this->getAttributeAndLocale($key);

        if (!$this->isTranslationAttribute($attribute)) {
            return parent::setAttribute($key, $value);
        }

        $this->setTranslationValue($locale, $attribute, $value);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        if (
            !$this->relationLoaded('translations')
            && !(bool) config('translatable.to_array_always_loads_translations', true)
        ) {
            return $attributes;
        }

        foreach ($this->translatedAttributes as $attribute) {
            if (in_array($attribute, $this->getHidden(), true)) {
                continue;
            }

            $attributes[$attribute] = $this->getAttributeOrFallback(null, $attribute);
        }

        return $attributes;
    }

    public function translation(): MorphMany
    {
        $locale = $this->locale();

        if (
            $this->useFallback()
            && !$this->translations()->where($this->getLocaleKey(), $locale)->exists()
            && $this->getFallbackLocale($locale)
        ) {
            $locale = $this->getFallbackLocale($locale);
        }

        return $this->translations()->where($this->getLocaleKey(), $locale);
    }

    public function translations(): MorphMany
    {
        return $this->morphMany($this->getTranslationModelName(), 'translatable');
    }

    public function getDefaultLocale(): ?string
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(?string $locale): self
    {
        $this->defaultLocale = $locale;

        return $this;
    }

    public function getLocaleKey(): string
    {
        $localeKey = config('translatable.locale_key', 'locale');

        return is_string($localeKey) && $localeKey !== '' ? $localeKey : 'locale';
    }

    public function getTranslationModelName(): string
    {
        $modelClass = config('translatable.translation_model', Translation::class);

        if (is_string($modelClass) && $modelClass !== '') {
            return $modelClass;
        }

        return Translation::class;
    }

    public function getNewTranslation(string $locale): TranslationBag
    {
        return new TranslationBag($this, $locale);
    }

    public function getTranslation(?string $locale = null, ?bool $withFallback = null): ?TranslationBag
    {
        $locale = $locale ?: $this->locale();
        $withFallback ??= $this->useFallback();

        if ($this->hasTranslation($locale)) {
            return new TranslationBag($this, $locale);
        }

        if (!$withFallback) {
            return null;
        }

        $fallbackLocale = $this->getFallbackLocale($locale);
        if ($fallbackLocale && $fallbackLocale !== $locale && $this->hasTranslation($fallbackLocale)) {
            return new TranslationBag($this, $fallbackLocale);
        }

        if ($fallbackLocale === null) {
            foreach ($this->getLocalesHelper()->all() as $configuredLocale) {
                if ($this->hasTranslation($configuredLocale)) {
                    return new TranslationBag($this, $configuredLocale);
                }
            }
        }

        return null;
    }

    public function getTranslationOrNew(?string $locale = null): TranslationBag
    {
        $locale = $locale ?: $this->locale();

        return $this->getTranslation($locale, false) ?? $this->getNewTranslation($locale);
    }

    public function getTranslationOrFail(string $locale): TranslationBag
    {
        $translation = $this->getTranslation($locale, false);

        if ($translation !== null) {
            return $translation;
        }

        throw (new ModelNotFoundException)->setModel($this->getTranslationModelName(), [$locale]);
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

    public function hasTranslation(?string $locale = null): bool
    {
        $locale = $locale ?: $this->locale();

        if ($this->relationLoaded('translations')) {
            return $this->translations->contains(function (Model $translation) use ($locale): bool {
                return $translation->getAttribute($this->getLocaleKey()) === $locale;
            });
        }

        return $this->translations()
            ->where($this->getLocaleKey(), $locale)
            ->exists();
    }

    public function isTranslationAttribute(string $key): bool
    {
        return in_array($key, $this->translatedAttributes, true);
    }

    public function isWrapperAttribute(string $key): bool
    {
        return $key === config('translatable.translations_wrapper');
    }

    /**
     * @param  string|array<int, string>|null  $locales
     */
    public function deleteTranslations(string|array|null $locales = null): void
    {
        if ($locales === null) {
            $translations = $this->translations()->get();
        } else {
            $translations = $this->translations()
                ->whereIn($this->getLocaleKey(), (array) $locales)
                ->get();
        }

        $translations->each->delete();
        $this->load('translations');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTranslationsArray(): array
    {
        $translations = [];
        $entries = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        foreach ($entries as $entry) {
            $translations[$entry->{$this->getLocaleKey()}][$entry->attribute] = $entry->value;
        }

        return $translations;
    }

    public function getTranslationValue(string $locale, string $attribute): mixed
    {
        $entry = $this->getTranslationEntry($locale, $attribute);

        return $entry?->value;
    }

    public function setTranslationValue(string $locale, string $attribute, mixed $value): void
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        if (!$this->relationLoaded('translations')) {
            $this->setRelation('translations', $translations);
        }

        $entry = $translations->first(function (Model $translation) use ($locale, $attribute): bool {
            return $translation->getAttribute($this->getLocaleKey()) === $locale
                && $translation->attribute === $attribute;
        });

        if (!$entry) {
            $modelClass = $this->getTranslationModelName();
            /** @var Model $entry */
            $entry = new $modelClass;
            $entry->setAttribute($this->getLocaleKey(), $locale);
            $entry->setAttribute('attribute', $attribute);
            $translations->add($entry);
        }

        $entry->setAttribute('value', $value);
    }

    public function replicateWithTranslations(?array $except = null): Model
    {
        $newInstance = $this->replicate($except);

        unset($newInstance->translations);
        $entries = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
        foreach ($entries as $entry) {
            $newInstance->translations->add($entry->replicate());
        }

        return $newInstance;
    }

    protected function locale(): string
    {
        if (is_string($this->defaultLocale) && $this->defaultLocale !== '') {
            return $this->defaultLocale;
        }

        return $this->getLocalesHelper()->current();
    }

    protected function getLocalesHelper(): Locales
    {
        return app(Locales::class);
    }

    protected function useFallback(): bool
    {
        return (bool) config('translatable.use_fallback', true);
    }

    protected function usePropertyFallback(): bool
    {
        return $this->useFallback() && (bool) config('translatable.use_property_fallback', true);
    }

    protected function getFallbackLocale(?string $locale = null): ?string
    {
        return $this->getLocalesHelper()->fallback($locale);
    }

    protected function getAttributeOrFallback(?string $locale, string $attribute): mixed
    {
        $locale = $locale ?: $this->locale();
        $value = $this->getTranslationValue($locale, $attribute);

        if (!$this->usePropertyFallback()) {
            return $value;
        }

        if ($value !== null && $value !== '') {
            return $value;
        }

        $fallbackLocale = $this->getFallbackLocale($locale);
        if ($fallbackLocale) {
            return $this->getTranslationValue($fallbackLocale, $attribute);
        }

        return $value;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function getAttributeAndLocale(string $key): array
    {
        if (!Str::contains($key, ':')) {
            return [$key, $this->locale()];
        }

        $parts = explode(':', $key, 2);

        return [$parts[0], $parts[1]];
    }

    protected function getTranslationEntry(string $locale, string $attribute): ?Model
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->first(function (Model $translation) use ($locale, $attribute): bool {
                return $translation->getAttribute($this->getLocaleKey()) === $locale
                    && $translation->attribute === $attribute;
            });
        }

        return $this->translations()
            ->where($this->getLocaleKey(), $locale)
            ->where('attribute', $attribute)
            ->first();
    }

    protected function saveTranslations(): bool
    {
        if (!$this->relationLoaded('translations')) {
            return true;
        }

        $saved = true;

        foreach ($this->translations as $translation) {
            if (!$saved || !$translation->isDirty()) {
                continue;
            }

            if (!empty($connectionName = $this->getConnectionName())) {
                $translation->setConnection($connectionName);
            }

            $translation->setAttribute('translatable_type', $this->getMorphClass());
            $translation->setAttribute('translatable_id', $this->getKey());
            $saved = $translation->save();
        }

        return $saved;
    }
}
