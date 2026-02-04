<?php

namespace PictaStudio\Translatable;

use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;

class TranslationBag
{
    protected TranslatableContract $translatable;

    public string $locale;

    public function __construct(TranslatableContract $translatable, string $locale)
    {
        $this->translatable = $translatable;
        $this->locale = $locale;
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function getAttribute(string $key)
    {
        if ($this->isLocaleKey($key)) {
            return $this->locale;
        }

        return $this->translatable->getTranslationValue($this->locale, $key);
    }

    public function setAttribute(string $key, $value): self
    {
        if ($this->isLocaleKey($key)) {
            $this->locale = $value;

            return $this;
        }

        if (! $this->translatable->isTranslationAttribute($key)) {
            return $this;
        }

        $this->translatable->setTranslationValue($this->locale, $key, $value);

        return $this;
    }

    public function save(): bool
    {
        return $this->translatable->save();
    }

    public function toArray(): array
    {
        $attributes = [];

        foreach ($this->translatable->translatedAttributes as $attribute) {
            $attributes[$attribute] = $this->getAttribute($attribute);
        }

        return $attributes;
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset($key): bool
    {
        if ($this->isLocaleKey($key)) {
            return true;
        }

        return in_array($key, $this->translatable->translatedAttributes, true);
    }

    protected function isLocaleKey(string $key): bool
    {
        return $key === 'locale' || $key === $this->translatable->getLocaleKey();
    }
}
