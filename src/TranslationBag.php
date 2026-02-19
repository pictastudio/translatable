<?php

namespace PictaStudio\Translatable;

use Illuminate\Database\Eloquent\Model;

class TranslationBag
{
    public function __construct(
        protected Model $translatable,
        public string $locale,
    ) {}

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        if ($this->isLocaleKey($key)) {
            return true;
        }

        return in_array($key, $this->translatable->translatedAttributes, true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        if ($this->isLocaleKey($key)) {
            return $this->locale;
        }

        return $this->translatable->getTranslationValue($this->locale, $key);
    }

    public function setAttribute(string $key, mixed $value): self
    {
        if ($this->isLocaleKey($key)) {
            $this->locale = (string) $value;

            return $this;
        }

        if (!$this->translatable->isTranslationAttribute($key)) {
            return $this;
        }

        $this->translatable->setTranslationValue($this->locale, $key, $value);

        return $this;
    }

    public function save(): bool
    {
        return $this->translatable->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = [];

        foreach ($this->translatable->translatedAttributes as $attribute) {
            $attributes[$attribute] = $this->getAttribute($attribute);
        }

        return $attributes;
    }

    protected function isLocaleKey(string $key): bool
    {
        return $key === 'locale' || $key === $this->translatable->getLocaleKey();
    }
}
