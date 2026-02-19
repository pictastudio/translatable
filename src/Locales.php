<?php

namespace PictaStudio\Translatable;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Translation\Translator;

class Locales
{
    /**
     * @var array<string, string>
     */
    protected array $locales = [];

    public function __construct(
        protected ConfigRepository $config,
        protected Translator $translator,
    ) {
        $this->load();
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_values($this->locales);
    }

    public function add(string $locale): void
    {
        if ($locale === '') {
            return;
        }

        $this->locales[$locale] = $locale;
    }

    public function forget(string $locale): void
    {
        unset($this->locales[$locale]);
    }

    public function get(string $locale): ?string
    {
        return $this->locales[$locale] ?? null;
    }

    public function getCountryLocale(string $locale, string $country): string
    {
        return $locale . $this->separator() . $country;
    }

    public function getLanguageFromCountryBasedLocale(string $locale): string
    {
        return explode($this->separator(), $locale, 2)[0];
    }

    public function isLocaleCountryBased(string $locale): bool
    {
        return str_contains($locale, $this->separator());
    }

    public function load(): void
    {
        $configuredLocales = (array) $this->config->get('translatable.locales', []);
        $separator = $this->separator();
        $locales = [];

        foreach ($configuredLocales as $key => $value) {
            if (is_string($key) && is_array($value)) {
                $locales[] = $key;

                foreach ($value as $region) {
                    if (is_string($region) && $region !== '') {
                        $locales[] = $key . $separator . $region;
                    }
                }

                continue;
            }

            if (is_string($value) && $value !== '') {
                $locales[] = $value;
            }
        }

        if ($locales === []) {
            $locale = $this->current();
            $this->locales = [$locale => $locale];

            return;
        }

        $this->locales = [];

        foreach (array_values(array_unique($locales)) as $locale) {
            $this->locales[$locale] = $locale;
        }
    }

    public function current(): string
    {
        $locale = $this->config->get('translatable.locale');

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        return $this->translator->getLocale();
    }

    public function separator(): string
    {
        $separator = $this->config->get('translatable.locale_separator');

        if (is_string($separator) && $separator !== '') {
            return $separator;
        }

        return '-';
    }

    public function has(?string $locale): bool
    {
        if (!is_string($locale) || $locale === '') {
            return false;
        }

        return isset($this->locales[$locale]);
    }

    public function fallback(?string $locale = null): ?string
    {
        if (is_string($locale) && $this->isLocaleCountryBased($locale)) {
            $language = $this->getLanguageFromCountryBasedLocale($locale);

            if ($this->has($language)) {
                return $language;
            }
        }

        $fallbackLocale = $this->config->get('translatable.fallback_locale');

        if (is_string($fallbackLocale) && $fallbackLocale !== '') {
            return $fallbackLocale;
        }

        return null;
    }
}
