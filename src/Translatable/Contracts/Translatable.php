<?php

namespace PictaStudio\Translatable\Contracts;

use PictaStudio\Translatable\TranslationBag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Translatable
{
    public static function defaultAutoloadTranslations(): void;

    public static function disableAutoloadTranslations(): void;

    public static function enableAutoloadTranslations(): void;

    public static function disableDeleteTranslationsCascade(): void;

    public static function enableDeleteTranslationsCascade(): void;

    /**
     * @param  string|array<string>|null  $locales
     */
    public function deleteTranslations(string|array|null $locales = null): void;

    public function getDefaultLocale(): ?string;

    public function getNewTranslation(string $locale): TranslationBag;

    public function getTranslation(?string $locale = null, ?bool $withFallback = null): ?TranslationBag;

    public function getTranslationOrNew(?string $locale = null): TranslationBag;

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getTranslationsArray(): array;

    public function hasTranslation(?string $locale = null): bool;

    public function isTranslationAttribute(string $key): bool;

    /**
     * @param  null|array<string>  $except
     */
    public function replicateWithTranslations(?array $except = null): Model;

    public function setDefaultLocale(?string $locale);

    public function translate(?string $locale = null, bool $withFallback = false): ?TranslationBag;

    public function translateOrDefault(?string $locale = null): ?TranslationBag;

    public function translateOrNew(?string $locale = null): TranslationBag;

    public function getTranslationValue(string $locale, string $attribute);

    public function setTranslationValue(string $locale, string $attribute, $value): void;

    public function translation(): MorphMany;

    public function translations(): MorphMany;
}
