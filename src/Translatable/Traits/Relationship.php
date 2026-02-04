<?php

namespace PictaStudio\Translatable\Traits;

use PictaStudio\Translatable\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property-read string $translationModel
 */
trait Relationship
{
    /**
     * @deprecated
     */
    public function getRelationKey(): string
    {
        return $this->getTranslationRelationKey();
    }

    /**
     * @internal will change to protected
     */
    public function getTranslationModelName(): string
    {
        return $this->translationModel ?: $this->getTranslationModelNameDefault();
    }

    /**
     * @internal will change to private
     */
    public function getTranslationModelNameDefault(): string
    {
        return config('translatable.translation_model', Translation::class);
    }

    /**
     * @internal will change to private
     */
    public function getTranslationModelNamespace(): ?string
    {
        return config('translatable.translation_model_namespace');
    }

    /**
     * @internal will change to protected
     */
    public function getTranslationRelationKey(): string
    {
        return 'translatable_id';
    }

    public function translation(): MorphMany
    {
        return $this
            ->translations()
            ->where($this->getLocaleKey(), $this->localeOrFallback());
    }

    public function translations(): MorphMany
    {
        return $this->morphMany($this->getTranslationModelName(), 'translatable');
    }

    protected function localeOrFallback()
    {
        return $this->useFallback() && ! $this->translations()->where($this->getLocaleKey(), $this->locale())->exists()
            ? $this->getFallbackLocale()
            : $this->locale();
    }
}
