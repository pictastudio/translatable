<?php

namespace PictaStudio\Translatable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

trait Scopes
{
    public function scopeListsTranslations(Builder $query, string $translationField)
    {
        $withFallback = $this->useFallback();
        $translationTable = $this->getTranslationsTable();
        $localeKey = $this->getLocaleKey();
        $attributeKey = 'attribute';
        $valueKey = 'value';
        $table = $this->getTable();
        $keyName = $this->getKeyName();
        $morphClass = $this->getMorphClass();

        $query
            ->select($table.'.'.$keyName, $translationTable.'.'.$valueKey.' as '.$translationField)
            ->leftJoin($translationTable, function (JoinClause $join) use ($translationTable, $table, $keyName, $morphClass, $attributeKey, $translationField) {
                $join
                    ->on($translationTable.'.translatable_id', '=', $table.'.'.$keyName)
                    ->where($translationTable.'.translatable_type', '=', $morphClass)
                    ->where($translationTable.'.'.$attributeKey, '=', $translationField);
            })
            ->where($translationTable.'.'.$localeKey, $this->locale());

        if ($withFallback) {
            $query->orWhere(function (Builder $q) use ($translationTable, $localeKey, $attributeKey, $translationField, $morphClass) {
                $q
                    ->where($translationTable.'.'.$localeKey, $this->getFallbackLocale())
                    ->where($translationTable.'.'.$attributeKey, $translationField)
                    ->where($translationTable.'.translatable_type', $morphClass)
                    ->whereNotIn($translationTable.'.translatable_id', function (QueryBuilder $q) use (
                        $translationTable,
                        $localeKey,
                        $attributeKey,
                        $translationField,
                        $morphClass
                    ) {
                        $q
                            ->select($translationTable.'.translatable_id')
                            ->from($translationTable)
                            ->where($translationTable.'.'.$localeKey, $this->locale())
                            ->where($translationTable.'.'.$attributeKey, $translationField)
                            ->where($translationTable.'.translatable_type', $morphClass);
                    });
            });
        }

        return $query;
    }

    public function scopeNotTranslatedIn(Builder $query, ?string $locale = null)
    {
        $locale = $locale ?: $this->locale();

        return $query->whereDoesntHave('translations', function (Builder $q) use ($locale) {
            $q->where($this->getLocaleKey(), '=', $locale);
        });
    }

    public function scopeOrderByTranslation(Builder $query, string $translationField, string $sortMethod = 'asc')
    {
        $translationTable = $this->getTranslationsTable();
        $localeKey = $this->getLocaleKey();
        $table = $this->getTable();
        $keyName = $this->getKeyName();
        $attributeKey = 'attribute';
        $valueKey = 'value';
        $morphClass = $this->getMorphClass();

        return $query
            ->with('translations')
            ->select("{$table}.*")
            ->leftJoin($translationTable, function (JoinClause $join) use ($translationTable, $localeKey, $table, $keyName, $attributeKey, $translationField, $morphClass) {
                $join
                    ->on("{$translationTable}.translatable_id", '=', "{$table}.{$keyName}")
                    ->where("{$translationTable}.translatable_type", $morphClass)
                    ->where("{$translationTable}.{$attributeKey}", $translationField)
                    ->where("{$translationTable}.{$localeKey}", $this->locale());
            })
            ->orderBy("{$translationTable}.{$valueKey}", $sortMethod);
    }

    public function scopeOrWhereTranslation(Builder $query, string $translationField, $value, ?string $locale = null)
    {
        return $this->scopeWhereTranslation($query, $translationField, $value, $locale, 'orWhereHas');
    }

    public function scopeOrWhereTranslationLike(Builder $query, string $translationField, $value, ?string $locale = null)
    {
        return $this->scopeWhereTranslation($query, $translationField, $value, $locale, 'orWhereHas', 'LIKE');
    }

    public function scopeTranslated(Builder $query)
    {
        return $query->has('translations');
    }

    public function scopeTranslatedIn(Builder $query, ?string $locale = null)
    {
        $locale = $locale ?: $this->locale();

        return $query->whereHas('translations', function (Builder $q) use ($locale) {
            $q->where($this->getLocaleKey(), '=', $locale);
        });
    }

    public function scopeWhereTranslation(Builder $query, string $translationField, $value, ?string $locale = null, string $method = 'whereHas', string $operator = '=')
    {
        return $query->$method('translations', function (Builder $query) use ($translationField, $value, $locale, $operator) {
            $query
                ->where($this->getTranslationsTable().'.attribute', $translationField)
                ->where($this->getTranslationsTable().'.value', $operator, $value);

            if ($locale) {
                $query->where($this->getTranslationsTable().'.'.$this->getLocaleKey(), $operator, $locale);
            }
        });
    }

    public function scopeWhereTranslationLike(Builder $query, string $translationField, $value, ?string $locale = null)
    {
        return $this->scopeWhereTranslation($query, $translationField, $value, $locale, 'whereHas', 'LIKE');
    }

    public function scopeWithTranslation(Builder $query, ?string $locale = null)
    {
        $locale = $locale ?: $this->locale();

        $query->with([
            'translations' => function (Relation $query) use ($locale) {
                if ($this->useFallback()) {
                    $countryFallbackLocale = $this->getFallbackLocale($locale); // e.g. de-DE => de
                    $locales = array_unique([$locale, $countryFallbackLocale, $this->getFallbackLocale()]);

                    return $query->whereIn($this->getTranslationsTable().'.'.$this->getLocaleKey(), $locales);
                }

                return $query->where($this->getTranslationsTable().'.'.$this->getLocaleKey(), $locale);
            },
        ]);
    }

    protected function getTranslationsTable(): string
    {
        return app()->make($this->getTranslationModelName())->getTable();
    }
}
