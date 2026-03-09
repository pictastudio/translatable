<?php

namespace PictaStudio\Translatable\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Locales;

class MissingTranslations
{
    public function __construct(
        protected Locales $locales,
        protected TranslatableModelRegistry $registry,
    ) {}

    /**
     * @param  array<int, class-string<Model&TranslatableContract>>  $modelClasses
     * @param  array{
     *     source_locale?: string|null,
     *     target_locales?: array<int, string>|null,
     *     per_page?: int|null,
     *     page?: int|null
     * }  $options
     * @return array{
     *     source_locale: string,
     *     target_locales: array<int, string>,
     *     paginator: LengthAwarePaginator,
     *     data: array<int, array{
     *         model_type: string,
     *         model_class: class-string<Model>,
     *         model_id: mixed,
     *         source_locale: string,
     *         target_locales: array<int, string>,
     *         translated_attributes: array<int, string>,
     *         source_values: array<string, string>,
     *         missing: array<string, array<int, string>>,
     *         missing_count: int
     *     }>
     * }
     */
    public function paginate(array $modelClasses, array $options = []): array
    {
        $sourceLocale = $this->resolveSourceLocale($options['source_locale'] ?? null);
        $targetLocales = $this->resolveTargetLocales($options['target_locales'] ?? null, $sourceLocale);
        $perPage = max(1, min((int) ($options['per_page'] ?? 50), 100));
        $page = max(1, (int) ($options['page'] ?? 1));

        $rows = collect();

        foreach ($modelClasses as $modelClass) {
            $rows = $rows->concat($this->rowsForModelClass($modelClass, $sourceLocale, $targetLocales));
        }

        $rows = $rows
            ->sortBy([
                ['model_type', 'asc'],
                ['model_id', 'asc'],
            ])
            ->values();

        $paginator = new Paginator(
            items: $rows->forPage($page, $perPage)->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
                'query' => request()->query(),
            ],
        );

        return [
            'source_locale' => $sourceLocale,
            'target_locales' => $targetLocales,
            'paginator' => $paginator,
            'data' => $paginator->items(),
        ];
    }

    /**
     * @return array<int, class-string<Model&TranslatableContract>>
     */
    public function allModelClasses(): array
    {
        return $this->registry->classes();
    }

    /**
     * @param  class-string<Model&TranslatableContract>  $modelClass
     * @return Collection<int, array{
     *     model_type: string,
     *     model_class: class-string<Model>,
     *     model_id: mixed,
     *     source_locale: string,
     *     target_locales: array<int, string>,
     *     translated_attributes: array<int, string>,
     *     source_values: array<string, string>,
     *     missing: array<string, array<int, string>>,
     *     missing_count: int
     * }>
     */
    protected function rowsForModelClass(string $modelClass, string $sourceLocale, array $targetLocales): Collection
    {
        /** @var Model&TranslatableContract $model */
        $model = new $modelClass;
        $attributes = $this->resolveAttributes($model);
        $baseColumns = $this->resolveBaseColumns($model, $attributes);
        $localeKey = $model->getLocaleKey();

        /** @var Collection<int, Model&TranslatableContract> $models */
        $models = $modelClass::query()
            ->where(function (Builder $query) use ($sourceLocale, $targetLocales, $attributes, $baseColumns, $localeKey): void {
                foreach ($targetLocales as $targetLocale) {
                    foreach ($attributes as $attribute) {
                        $query->orWhere(function (Builder $pairQuery) use (
                            $sourceLocale,
                            $targetLocale,
                            $attribute,
                            $baseColumns,
                            $localeKey
                        ): void {
                            $pairQuery
                                ->where(function (Builder $sourceQuery) use (
                                    $sourceLocale,
                                    $attribute,
                                    $baseColumns,
                                    $localeKey
                                ): void {
                                    $sourceQuery->whereHas('translations', function (Builder $translations) use (
                                        $sourceLocale,
                                        $attribute,
                                        $localeKey
                                    ): void {
                                        $translations
                                            ->where($localeKey, $sourceLocale)
                                            ->where('attribute', $attribute);

                                        $this->whereNotBlank($translations, 'value');
                                    });

                                    if ($baseColumns[$attribute] ?? false) {
                                        $sourceQuery->orWhere(function (Builder $columnQuery) use ($attribute): void {
                                            $this->whereNotBlank($columnQuery, $attribute);
                                        });
                                    }
                                })
                                ->whereDoesntHave('translations', function (Builder $translations) use (
                                    $targetLocale,
                                    $attribute,
                                    $localeKey
                                ): void {
                                    $translations
                                        ->where($localeKey, $targetLocale)
                                        ->where('attribute', $attribute);

                                    $this->whereNotBlank($translations, 'value');
                                });
                        });
                    }
                }
            })
            ->orderBy($model->getQualifiedKeyName())
            ->get();

        $models->load([
            'translations' => function ($query) use ($localeKey, $sourceLocale, $targetLocales, $attributes): void {
                $query
                    ->whereIn($localeKey, array_values(array_unique([...$targetLocales, $sourceLocale])))
                    ->whereIn('attribute', $attributes);
            },
        ]);

        return $models
            ->map(function (Model $entry) use ($modelClass, $sourceLocale, $targetLocales, $attributes, $baseColumns): array {
                /** @var Model&TranslatableContract $entry */
                $sourceValues = [];

                foreach ($attributes as $attribute) {
                    $sourceValue = $this->extractSourceValue($entry, $sourceLocale, $attribute, $baseColumns[$attribute] ?? false);

                    if ($sourceValue !== null) {
                        $sourceValues[$attribute] = $sourceValue;
                    }
                }

                $missing = [];

                foreach ($targetLocales as $targetLocale) {
                    $missingAttributes = [];

                    foreach ($attributes as $attribute) {
                        if (!array_key_exists($attribute, $sourceValues)) {
                            continue;
                        }

                        if ($this->normalizeNullableString($entry->getTranslationValue($targetLocale, $attribute)) === null) {
                            $missingAttributes[] = $attribute;
                        }
                    }

                    if ($missingAttributes !== []) {
                        $missing[$targetLocale] = $missingAttributes;
                    }
                }

                return [
                    'model_type' => $this->registry->aliasFor($modelClass),
                    'model_class' => $modelClass,
                    'model_id' => $entry->getKey(),
                    'source_locale' => $sourceLocale,
                    'target_locales' => $targetLocales,
                    'translated_attributes' => $attributes,
                    'source_values' => $sourceValues,
                    'missing' => $missing,
                    'missing_count' => array_sum(array_map('count', $missing)),
                ];
            })
            ->filter(static fn (array $row): bool => $row['missing_count'] > 0)
            ->values();
    }

    protected function resolveSourceLocale(?string $sourceLocale): string
    {
        $sourceLocale = $this->normalizeNullableString($sourceLocale) ?? $this->locales->current();

        if (!$this->locales->has($sourceLocale)) {
            throw new InvalidArgumentException("The source locale [{$sourceLocale}] is not configured as translatable.");
        }

        return $sourceLocale;
    }

    /**
     * @param  array<int, string>|null  $targetLocales
     * @return array<int, string>
     */
    protected function resolveTargetLocales(?array $targetLocales, string $sourceLocale): array
    {
        $targetLocales = $this->normalizeStringArray($targetLocales ?? []);

        if ($targetLocales === []) {
            $targetLocales = array_values(array_filter(
                $this->locales->all(),
                static fn (string $locale): bool => $locale !== $sourceLocale
            ));
        }

        foreach ($targetLocales as $locale) {
            if (!$this->locales->has($locale)) {
                throw new InvalidArgumentException("The target locale [{$locale}] is not configured as translatable.");
            }
        }

        return array_values(array_filter(
            $targetLocales,
            static fn (string $locale): bool => $locale !== $sourceLocale
        ));
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAttributes(Model&TranslatableContract $model): array
    {
        $attributes = $this->normalizeStringArray($model->translatedAttributes);

        if ($attributes === []) {
            throw new InvalidArgumentException('At least one translated attribute must be provided.');
        }

        return $attributes;
    }

    /**
     * @param  array<int, string>  $attributes
     * @return array<string, bool>
     */
    protected function resolveBaseColumns(Model $model, array $attributes): array
    {
        $columns = [];
        $table = $model->getTable();

        foreach ($attributes as $attribute) {
            $columns[$attribute] = Schema::hasColumn($table, $attribute);
        }

        return $columns;
    }

    protected function extractSourceValue(
        Model&TranslatableContract $model,
        string $sourceLocale,
        string $attribute,
        bool $hasBaseColumn,
    ): ?string {
        $translatedValue = $this->normalizeNullableString($model->getTranslationValue($sourceLocale, $attribute));

        if ($translatedValue !== null) {
            return $translatedValue;
        }

        if (!$hasBaseColumn) {
            return null;
        }

        return $this->normalizeNullableString($model->getRawOriginal($attribute));
    }

    protected function whereNotBlank(Builder $query, string $column): void
    {
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        $query
            ->whereNotNull($column)
            ->whereRaw("trim({$wrappedColumn}) <> ''");
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    protected function normalizeStringArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $value = $this->normalizeNullableString($value);

            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }
}
