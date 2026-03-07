<?php

namespace PictaStudio\Translatable\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\{Locales, Translatable as TranslatableTrait};
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function Laravel\Prompts\{confirm, multiselect, select};

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

    public function handle(ModelTranslator $translator, Locales $locales): int
    {
        $modelClass = $this->argument('model') ?: $this->promptForModelClass();

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

        foreach ($models as $model) {
            $summary = $translator->translate($model, [
                'source_locale' => $sourceLocale,
                'target_locales' => $targetLocales,
                'attributes' => $attributes,
                'force' => $force,
                'provider' => $this->option('provider'),
                'model' => $this->option('ai-model'),
            ]);

            if ($summary['translated_count'] === 0) {
                $this->components->warn("Skipped {$modelClass}#{$model->getKey()} (nothing to translate).");

                continue;
            }

            $translatedModels++;
            $translatedPairs += $summary['translated_count'];

            $this->components->info(
                "Translated {$modelClass}#{$model->getKey()} " .
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

    protected function promptForModelClass(): ?string
    {
        $models = $this->discoverTranslatableModels();

        if ($models === []) {
            return null;
        }

        if (count($models) === 1) {
            return $models[0];
        }

        return select(
            label: 'Which model would you like to translate?',
            options: $models,
        );
    }

    /**
     * @return array<int, class-string<Model&TranslatableContract>>
     */
    protected function discoverTranslatableModels(): array
    {
        $models = [];

        foreach ($this->autoloadClassCandidates() as $class) {
            if ($this->isTranslatableModelClass($class) && $this->usesTranslatableTrait($class)) {
                $models[$class] = $class;
            }
        }

        foreach (get_declared_classes() as $class) {
            if ($this->isTranslatableModelClass($class) && $this->usesTranslatableTrait($class)) {
                $models[$class] = $class;
            }
        }

        ksort($models);

        return array_values($models);
    }

    /**
     * @return array<int, class-string>
     */
    protected function autoloadClassCandidates(): array
    {
        $composerPath = base_path('composer.json');

        if (!is_file($composerPath)) {
            return [];
        }

        /** @var array{autoload?: array{psr-4?: array<string, string|array<int, string>>}}|null $composer */
        $composer = json_decode((string) file_get_contents($composerPath), true);
        $psr4 = Arr::get($composer, 'autoload.psr-4', []);

        if (!is_array($psr4)) {
            return [];
        }

        $classes = [];

        foreach ($psr4 as $namespace => $paths) {
            if (!is_string($namespace)) {
                continue;
            }

            foreach ((array) $paths as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $classes = array_merge($classes, $this->classesFromDirectory($namespace, base_path($path)));
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @return array<int, class-string>
     */
    protected function classesFromDirectory(string $namespace, string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $class = $namespace . str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath
            );

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    protected function usesTranslatableTrait(string $modelClass): bool
    {
        return in_array(TranslatableTrait::class, class_uses_recursive($modelClass), true);
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
