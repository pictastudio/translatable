<?php

namespace PictaStudio\Translatable\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Support\MissingTranslations;

class TranslateMissingCommand extends Command
{
    protected $signature = 'translatable:translate-missing
        {--source-locale= : Locale to translate from}
        {--target-locales=* : Target locales to translate into}
        {--provider= : Laravel AI provider override}
        {--ai-model= : Laravel AI model override}';

    protected $description = 'Translate all missing translations for all translatable models using the Laravel AI SDK';

    public function handle(
        MissingTranslations $missingTranslations,
        ModelTranslator $translator,
    ): int {
        $results = $missingTranslations->collect(
            $missingTranslations->allModelClasses(),
            [
                'source_locale' => $this->option('source-locale'),
                'target_locales' => $this->option('target-locales'),
                'accepted' => true,
            ],
        );

        if ($results['data'] === []) {
            $this->components->info('No missing translations were found.');

            return self::SUCCESS;
        }

        $translatedModels = 0;
        $translatedPairs = 0;

        collect($results['data'])
            ->groupBy('model_class')
            ->each(function (Collection $rows, string $modelClass) use (
                $translator,
                $results,
                &$translatedModels,
                &$translatedPairs
            ): void {
                /** @var class-string<Model&TranslatableContract> $modelClass */
                $ids = $rows->pluck('model_id')->all();
                $attributes = $rows
                    ->pluck('translated_attributes')
                    ->flatten()
                    ->unique()
                    ->values()
                    ->all();

                $models = $modelClass::query()->whereKey($ids)->get();

                if ($models->isEmpty()) {
                    return;
                }

                $summaries = $translator->translateMany($models->all(), [
                    'source_locale' => $results['source_locale'],
                    'target_locales' => $results['target_locales'],
                    'attributes' => $attributes,
                    'force' => false,
                    'provider' => $this->option('provider'),
                    'model' => $this->option('ai-model'),
                ]);

                foreach ($summaries as $summary) {
                    if ($summary['translated_count'] === 0) {
                        continue;
                    }

                    $translatedModels++;
                    $translatedPairs += $summary['translated_count'];

                    $this->components->info(
                        "Translated {$modelClass}#{$summary['model_id']} " .
                        "({$summary['translated_count']} field(s), source: {$summary['source_locale']})."
                    );
                }
            });

        $this->components->info("Updated {$translatedModels} model(s) with {$translatedPairs} translated field(s).");

        return self::SUCCESS;
    }
}
