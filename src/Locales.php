<?php

namespace PictaStudio\Translatable;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Translation\Translator;

class Locales
{
    public function __construct(
        protected ConfigRepository $config,
        protected Translator $translator,
    ) {}

    /**
     * @return array<int, string>
     */
    public function all(): array
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
            return [$this->current()];
        }

        return array_values(array_unique($locales));
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

        return in_array($locale, $this->all(), true);
    }

    public function fallback(?string $locale = null): ?string
    {
        if (is_string($locale) && str_contains($locale, $this->separator())) {
            $language = explode($this->separator(), $locale, 2)[0];

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
