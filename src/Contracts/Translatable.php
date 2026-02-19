<?php

namespace PictaStudio\Translatable\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use PictaStudio\Translatable\TranslationBag;

interface Translatable
{
    /**
     * @param  string|array<int, string>|null  $locales
     */
    public function deleteTranslations(string|array|null $locales = null): void;

    public function getDefaultLocale(): ?string;

    public function getLocaleKey(): string;

    public function getTranslationModelName(): string;

    public function getNewTranslation(string $locale): TranslationBag;

    public function getTranslation(?string $locale = null, ?bool $withFallback = null): ?TranslationBag;

    public function getTranslationOrNew(?string $locale = null): TranslationBag;

    public function getTranslationOrFail(string $locale): TranslationBag;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTranslationsArray(): array;

    public function hasTranslation(?string $locale = null): bool;

    public function isTranslationAttribute(string $key): bool;

    public function isWrapperAttribute(string $key): bool;

    /**
     * @param  array<int, string>|null  $except
     */
    public function replicateWithTranslations(?array $except = null): Model;

    public function setDefaultLocale(?string $locale): self;

    public function translate(?string $locale = null, bool $withFallback = false): ?TranslationBag;

    public function translateOrDefault(?string $locale = null): ?TranslationBag;

    public function translateOrNew(?string $locale = null): TranslationBag;

    public function translateOrFail(string $locale): TranslationBag;

    public function getTranslationValue(string $locale, string $attribute): mixed;

    public function setTranslationValue(string $locale, string $attribute, mixed $value): void;

    public function translation(): MorphMany;

    public function translations(): MorphMany;
}
