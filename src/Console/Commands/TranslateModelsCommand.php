<?php

namespace PictaStudio\Translatable\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Locales;
use PictaStudio\Translatable\Support\TranslatableModelRegistry;

use function Laravel\Prompts\{confirm, multiselect, search};

class TranslateModelsCommand extends Command
{
    protected $signature = 'translatable:translate
        {model? : The fully qualified model class name}
        {--ids=* : Limit translation to the given model keys}
        {--source-locale= : Locale to translate from}
        {--target-locales=* : Target locales to translate into}
        {--attributes=* : Specific translated attributes to translate}
        {--force : Overwrite existing translations}
        {--provider= : Laravel AI provider override}
        {--ai-model= : Laravel AI model override}';

    protected $description = 'Translate translatable models using the Laravel AI SDK';

    public function handle(ModelTranslator $translator, Locales $locales, TranslatableModelRegistry $registry): int
    {
        $modelClass = $this->argument('model') ?: $this->promptForModelClass($registry);

        if ($modelClass === null) {
            $this->components->error('No translatable models that use the translatable trait were found in this project.');

            return self::FAILURE;
        }

        if (!$this->isTranslatableModelClass($modelClass)) {
            $this->components->error("The model [{$modelClass}] must extend Eloquent and implement the translatable contract.");

            return self::FAILURE;
        }

        $sourceLocale = $this->option('source-locale') ?: $locales->current();
        $targetLocales = $this->option('target-locales') ?: $this->promptForTargetLocales($sourceLocale, $locales);
        $attributes = $this->option('attributes') ?: $this->promptForAttributes($modelClass);
        $force = $this->option('force') || $this->promptForForce();

        /** @var class-string<Model&TranslatableContract> $modelClass */
        $models = $modelClass::query()
            ->when($this->option('ids') !== [], fn ($query) => $query->whereKey($this->option('ids')))
            ->get();

        if ($models->isEmpty()) {
            $this->components->error("No models were found for [{$modelClass}].");

            return self::FAILURE;
        }

        $translatedModels = 0;
        $translatedPairs = 0;
        $summaries = $translator->translateMany($models->all(), [
            'source_locale' => $sourceLocale,
            'target_locales' => $targetLocales,
            'attributes' => $attributes,
            'force' => $force,
            'provider' => $this->option('provider'),
            'model' => $this->option('ai-model'),
        ]);

        foreach ($summaries as $summary) {
            if ($summary['translated_count'] === 0) {
                $this->components->warn("Skipped {$modelClass}#{$summary['model_id']} (nothing to translate).");

                continue;
            }

            $translatedModels++;
            $translatedPairs += $summary['translated_count'];

            $this->components->info(
                "Translated {$modelClass}#{$summary['model_id']} " .
                "({$summary['translated_count']} field(s), source: {$summary['source_locale']})."
            );
        }

        $this->components->info("Updated {$translatedModels} model(s) with {$translatedPairs} translated field(s).");

        return self::SUCCESS;
    }

    protected function isTranslatableModelClass(mixed $modelClass): bool
    {
        return is_string($modelClass)
            && class_exists($modelClass)
            && is_subclass_of($modelClass, Model::class)
            && is_subclass_of($modelClass, TranslatableContract::class);
    }

    protected function promptForModelClass(TranslatableModelRegistry $registry): ?string
    {
        $models = $registry->classes();

        if ($models === []) {
            return null;
        }

        if (count($models) === 1) {
            return $models[0];
        }

        return search(
            label: 'Which model would you like to translate?',
            options: function (string $value) use ($models): array {
                if (mb_trim($value) === '') {
                    return array_values($models);
                }

                return array_values(array_filter(
                    $models,
                    static fn (string $model): bool => mb_stripos($model, $value) !== false
                ));
            },
        );
    }

    /**
     * @return array<int, string>
     */
    protected function promptForTargetLocales(string $sourceLocale, Locales $locales): array
    {
        $availableLocales = array_values(array_filter(
            $locales->all(),
            static fn (string $locale): bool => $locale !== $sourceLocale
        ));

        if ($availableLocales === []) {
            return [];
        }

        return multiselect(
            label: 'Which locales would you like to translate into?',
            options: $availableLocales,
            required: 'Select at least one target locale.',
        );
    }

    /**
     * @param  class-string<Model&TranslatableContract>  $modelClass
     * @return array<int, string>
     */
    protected function promptForAttributes(string $modelClass): array
    {
        /** @var Model&TranslatableContract $model */
        $model = new $modelClass;

        if ($model->translatedAttributes === []) {
            return [];
        }

        return multiselect(
            label: 'Which attributes would you like to translate?',
            options: $model->translatedAttributes,
            required: 'Select at least one attribute.',
        );
    }

    protected function promptForForce(): bool
    {
        return confirm(
            label: 'Overwrite existing translations?',
            default: false,
        );
    }
}
