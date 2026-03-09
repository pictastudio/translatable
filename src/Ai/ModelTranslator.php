<?php

namespace PictaStudio\Translatable\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Locales;
use RuntimeException;
use Traversable;

class ModelTranslator
{
    public function __construct(
        protected Locales $locales,
    ) {}

    /**
     * @param  array{
     *      source_locale?: string|null,
     *      target_locales?: array<int, string>|null,
     *      attributes?: array<int, string>|null,
     *      force?: bool,
     *      provider?: string|array<int, string>|null,
     *      model?: string|null
     *  }  $options
     * @return array{
     *      model_type: class-string<Model>,
     *      model_id: mixed,
     *      source_locale: string,
     *      target_locales: array<int, string>,
     *      translated_attributes: array<int, string>,
     *      requested_count: int,
     *      translated_count: int,
     *      translated: array<string, array<string, string>>,
     *      skipped: array<int, array{locale: string, attribute: string, reason: string}>
     * }
     */
    public function translate(Model&TranslatableContract $model, array $options = []): array
    {
        $results = $this->translateMany([$model], $options);

        return $results[0];
    }

    /**
     * @param  iterable<Model&TranslatableContract>  $models
     * @param  array{
     *      source_locale?: string|null,
     *      target_locales?: array<int, string>|null,
     *      attributes?: array<int, string>|null,
     *      force?: bool,
     *      provider?: string|array<int, string>|null,
     *      model?: string|null
     *  }  $options
     * @return array<int, array{
     *      model_type: class-string<Model>,
     *      model_id: mixed,
     *      source_locale: string,
     *      target_locales: array<int, string>,
     *      translated_attributes: array<int, string>,
     *      requested_count: int,
     *      translated_count: int,
     *      translated: array<string, array<string, string>>,
     *      skipped: array<int, array{locale: string, attribute: string, reason: string}>
     * }>
     */
    public function translateMany(iterable $models, array $options = []): array
    {
        $models = $this->normalizeModels($models);

        if ($models === []) {
            return [];
        }

        $this->ensureModelsCanBeBatched($models);

        $sourceLocale = $this->resolveSourceLocale(Arr::get($options, 'source_locale'));
        $targetLocales = $this->resolveTargetLocales(Arr::get($options, 'target_locales'), $sourceLocale);
        $attributes = $this->resolveAttributes($models[0], Arr::get($options, 'attributes'));
        $provider = Arr::get($options, 'provider', config('translatable.ai.provider'));
        $aiModel = $this->normalizeNullableString(Arr::get($options, 'model', config('translatable.ai.model')));
        $force = (bool) Arr::get($options, 'force', false);

        $preparedModels = [];

        foreach ($models as $model) {
            $sourceValues = $this->resolveSourceValues($model, $sourceLocale, $attributes);
            [$pairs, $skipped] = $this->resolvePairs(
                $model,
                $attributes,
                $sourceValues,
                $targetLocales,
                $force
            );

            $preparedModels[] = [
                'model' => $model,
                'model_id' => (string) $model->getKey(),
                'source_values' => $sourceValues,
                'pairs' => $pairs,
                'skipped' => $skipped,
            ];
        }

        $translationsByModelId = [];
        $translatableBatch = array_values(array_filter(
            $preparedModels,
            static fn (array $item): bool => $item['pairs'] !== []
        ));

        $batchSize = max(1, (int) config('translatable.ai.batch_size', 25));

        foreach (array_chunk($translatableBatch, $batchSize) as $batch) {
            $batchTranslations = $this->requestTranslationsForBatch(
                $models[0]::class,
                $sourceLocale,
                $targetLocales,
                $attributes,
                $batch,
                $provider,
                $aiModel,
            );

            foreach ($batchTranslations as $modelId => $translations) {
                $translationsByModelId[$modelId] = $translations;
            }
        }

        $results = [];

        foreach ($preparedModels as $item) {
            /** @var Model&TranslatableContract $model */
            $model = $item['model'];
            $translations = $translationsByModelId[$item['model_id']] ?? [];

            foreach ($translations as $locale => $localeTranslations) {
                foreach ($localeTranslations as $attribute => $value) {
                    $model->setTranslationValue($locale, $attribute, $value);
                }
            }

            if ($translations !== []) {
                $model->save();
            }

            $results[] = $this->summary(
                $model,
                $sourceLocale,
                $targetLocales,
                $attributes,
                $translations,
                $item['skipped'],
                count($item['pairs']),
            );
        }

        return $results;
    }

    protected function resolveSourceLocale(?string $sourceLocale): string
    {
        $sourceLocale = $this->normalizeNullableString($sourceLocale)
            ?? $this->normalizeNullableString(config('translatable.ai.source_locale'))
            ?? $this->locales->fallback()
            ?? $this->locales->current();

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
     * @param  array<int, string>|null  $attributes
     * @return array<int, string>
     */
    protected function resolveAttributes(Model&TranslatableContract $model, ?array $attributes): array
    {
        $attributes = $this->normalizeStringArray($attributes ?? $model->translatedAttributes);

        if ($attributes === []) {
            throw new InvalidArgumentException('At least one translated attribute must be provided.');
        }

        foreach ($attributes as $attribute) {
            if (!$model->isTranslationAttribute($attribute)) {
                throw new InvalidArgumentException(
                    "The attribute [{$attribute}] is not marked as translatable on [" . $model::class . '].'
                );
            }
        }

        return $attributes;
    }

    /**
     * @param  array<int, string>  $attributes
     * @return array<string, string>
     */
    protected function resolveSourceValues(Model&TranslatableContract $model, string $sourceLocale, array $attributes): array
    {
        $values = [];

        foreach ($attributes as $attribute) {
            $value = $this->extractSourceValue($model, $sourceLocale, $attribute);

            if ($value !== null) {
                $values[$attribute] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $attributes
     * @param  array<string, string>  $sourceValues
     * @param  array<int, string>  $targetLocales
     * @return array{0: array<int, array{locale: string, attribute: string}>, 1: array<int, array{locale: string, attribute: string, reason: string}>}
     */
    protected function resolvePairs(
        Model&TranslatableContract $model,
        array $attributes,
        array $sourceValues,
        array $targetLocales,
        bool $force,
    ): array {
        $pairs = [];
        $skipped = [];

        foreach ($targetLocales as $locale) {
            foreach ($attributes as $attribute) {
                if (!array_key_exists($attribute, $sourceValues)) {
                    $skipped[] = [
                        'locale' => $locale,
                        'attribute' => $attribute,
                        'reason' => 'missing_source_value',
                    ];

                    continue;
                }

                $existingValue = $model->getTranslationValue($locale, $attribute);

                if (!$force && $this->normalizeTranslatableValue($existingValue) !== null) {
                    $skipped[] = [
                        'locale' => $locale,
                        'attribute' => $attribute,
                        'reason' => 'existing_translation',
                    ];

                    continue;
                }

                $pairs[] = [
                    'locale' => $locale,
                    'attribute' => $attribute,
                ];
            }
        }

        return [$pairs, $skipped];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $targetLocales
     * @param  array<int, string>  $attributes
     * @param  array<int, array{
     *      model: Model&TranslatableContract,
     *      model_id: string,
     *      source_values: array<string, string>,
     *      pairs: array<int, array{locale: string, attribute: string}>,
     *      skipped: array<int, array{locale: string, attribute: string, reason: string}>
     *  }>  $batch
     * @param  string|array<int, string>|null  $provider
     * @return array<string, array<string, array<string, string>>>
     */
    protected function requestTranslationsForBatch(
        string $modelClass,
        string $sourceLocale,
        array $targetLocales,
        array $attributes,
        array $batch,
        string|array|null $provider,
        ?string $aiModel,
    ): array {
        $translationCount = array_sum(array_map(
            static fn (array $item): int => count($item['pairs']),
            $batch
        ));

        $agent = TranslateModelAgent::make(
            sourceLocale: $sourceLocale,
            modelIds: array_values(array_map(
                static fn (array $item): string => $item['model_id'],
                $batch
            )),
            targetLocales: $targetLocales,
            attributes: $attributes,
            translationCount: $translationCount,
        );

        $response = $agent->prompt(
            $this->buildBatchPrompt($modelClass, $sourceLocale, $batch),
            provider: $provider,
            model: $aiModel,
        );

        /** @var mixed $translations */
        $translations = $response['translations'] ?? null;

        if (!is_array($translations)) {
            throw new RuntimeException('The AI translation response did not include a valid translations payload.');
        }

        return $this->normalizeTranslations($translations, $batch);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, array{
     *      model: Model&TranslatableContract,
     *      model_id: string,
     *      source_values: array<string, string>,
     *      pairs: array<int, array{locale: string, attribute: string}>,
     *      skipped: array<int, array{locale: string, attribute: string, reason: string}>
     *  }>  $batch
     */
    protected function buildBatchPrompt(string $modelClass, string $sourceLocale, array $batch): string
    {
        $payload = [
            'model_type' => $modelClass,
            'source_locale' => $sourceLocale,
            'models' => array_map(
                static fn (array $item): array => [
                    'model_id' => $item['model_id'],
                    'source_values' => $item['source_values'],
                    'translations_to_generate' => $item['pairs'],
                ],
                $batch
            ),
        ];

        return implode("\n\n", [
            'Translate the provided Laravel translatable model fields into the requested locales.',
            'Requirements:',
            '- Preserve placeholders, HTML, Markdown, URLs, email addresses, punctuation, and line breaks.',
            '- Keep the same meaning, register, and amount of detail.',
            '- Return exactly one translation for each model_id, locale, and attribute combination listed in the payload.',
            '- Do not add explanations or code fences.',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    /**
     * @param  array<int, mixed>  $translations
     * @param  array<int, array{
     *      model: Model&TranslatableContract,
     *      model_id: string,
     *      source_values: array<string, string>,
     *      pairs: array<int, array{locale: string, attribute: string}>,
     *      skipped: array<int, array{locale: string, attribute: string, reason: string}>
     *  }>  $batch
     * @return array<string, array<string, array<string, string>>>
     */
    protected function normalizeTranslations(array $translations, array $batch): array
    {
        $requestedPairs = [];

        foreach ($batch as $item) {
            foreach ($item['pairs'] as $pair) {
                $requestedPairs[$item['model_id'] . '|' . $pair['locale'] . '|' . $pair['attribute']] = true;
            }
        }

        $normalized = [];

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $modelId = $this->normalizeNullableString(Arr::get($translation, 'model_id'));
            $locale = $this->normalizeNullableString(Arr::get($translation, 'locale'));
            $attribute = $this->normalizeNullableString(Arr::get($translation, 'attribute'));
            $value = $this->normalizeNullableString(Arr::get($translation, 'value'));

            if ($modelId === null || $locale === null || $attribute === null || $value === null) {
                continue;
            }

            $pairKey = $modelId . '|' . $locale . '|' . $attribute;

            if (!isset($requestedPairs[$pairKey])) {
                continue;
            }

            $normalized[$modelId][$locale][$attribute] = $value;
        }

        foreach ($batch as $item) {
            foreach ($item['pairs'] as $pair) {
                $value = $normalized[$item['model_id']][$pair['locale']][$pair['attribute']] ?? null;

                if ($value === null || mb_trim($value) === '') {
                    throw new RuntimeException(
                        "The AI response did not return a translation for [{$item['model_id']}:{$pair['locale']}:{$pair['attribute']}]."
                    );
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $targetLocales
     * @param  array<int, string>  $attributes
     * @param  array<string, array<string, string>>  $translations
     * @param  array<int, array{locale: string, attribute: string, reason: string}>  $skipped
     * @return array{
     *      model_type: class-string<Model>,
     *      model_id: mixed,
     *      source_locale: string,
     *      target_locales: array<int, string>,
     *      translated_attributes: array<int, string>,
     *      requested_count: int,
     *      translated_count: int,
     *      translated: array<string, array<string, string>>,
     *      skipped: array<int, array{locale: string, attribute: string, reason: string}>
     * }
     */
    protected function summary(
        Model&TranslatableContract $model,
        string $sourceLocale,
        array $targetLocales,
        array $attributes,
        array $translations,
        array $skipped,
        int $requestedCount,
    ): array {
        $translatedCount = 0;

        foreach ($translations as $localeTranslations) {
            $translatedCount += count($localeTranslations);
        }

        return [
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'source_locale' => $sourceLocale,
            'target_locales' => $targetLocales,
            'translated_attributes' => $attributes,
            'requested_count' => $requestedCount,
            'translated_count' => $translatedCount,
            'translated' => $translations,
            'skipped' => $skipped,
        ];
    }

    protected function extractSourceValue(Model&TranslatableContract $model, string $sourceLocale, string $attribute): ?string
    {
        $translatedValue = $this->normalizeTranslatableValue($model->getTranslationValue($sourceLocale, $attribute));

        if ($translatedValue !== null) {
            return $translatedValue;
        }

        return $this->normalizeTranslatableValue($model->getRawOriginal($attribute));
    }

    protected function normalizeTranslatableValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return mb_trim($value) === '' ? null : $value;
        }

        if (is_scalar($value)) {
            $value = (string) $value;

            return mb_trim($value) === '' ? null : $value;
        }

        return null;
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
     * @param  iterable<Model&TranslatableContract>  $models
     * @return array<int, Model&TranslatableContract>
     */
    protected function normalizeModels(iterable $models): array
    {
        if ($models instanceof Traversable) {
            /** @var array<int, Model&TranslatableContract> $models */
            $models = iterator_to_array($models, false);
        }

        return array_values($models);
    }

    /**
     * @param  array<int, Model&TranslatableContract>  $models
     */
    protected function ensureModelsCanBeBatched(array $models): void
    {
        $modelClass = $models[0]::class;

        foreach ($models as $model) {
            if ($model::class !== $modelClass) {
                throw new InvalidArgumentException('Batched AI translations require all models to be the same class.');
            }
        }
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
