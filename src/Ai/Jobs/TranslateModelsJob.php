<?php

namespace PictaStudio\Translatable\Ai\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Contracts\{Translatable as TranslatableContract, TranslationRequestNotifier};
use Throwable;

class TranslateModelsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  class-string<Model&TranslatableContract>  $modelClass
     * @param  array<int, int|string>  $ids
     * @param  array{
     *     source_locale?: string|null,
     *     target_locales?: array<int, string>|null,
     *     attributes?: array<int, string>|null,
     *     force?: bool,
     *     provider?: string|array<int, string>|null,
     *     model?: string|null
     * }  $options
     */
    public function __construct(
        public string $requestedModel,
        public string $modelClass,
        public array $ids,
        public array $options = [],
        public mixed $notifiable = null,
    ) {
        try {
            $this->onConnection(config('translatable.ai.queue.connection'));
            $this->onQueue((string) config('translatable.ai.queue.name', 'default'));
        } catch (Throwable) {
            $this->onQueue('default');
        }
    }

    public function handle(ModelTranslator $translator): void
    {
        /** @var class-string<Model&TranslatableContract> $modelClass */
        $modelClass = $this->modelClass;

        try {
            $models = $modelClass::query()->whereKey($this->ids)->get();
        } catch (Throwable) {
            return;
        }

        if ($models->isEmpty()) {
            return;
        }

        try {
            $results = $translator->translateMany($models->all(), $this->options);
        } catch (Throwable) {
            return;
        }

        $firstResult = $results[0] ?? null;

        if (!$this->notificationsEnabled()) {
            return;
        }

        if (!is_object($this->notifiable)) {
            return;
        }

        try {
            $notifier = $this->resolveNotifier(app());

            if (!$notifier instanceof TranslationRequestNotifier) {
                return;
            }

            $notifier->notify($this->notifiable, [
                'model' => $this->requestedModel,
                'model_class' => $this->modelClass,
                'requested_ids' => $this->ids,
                'matched_models' => count($results),
                'translated_pairs' => array_sum(array_column($results, 'translated_count')),
                'source_locale' => is_array($firstResult) ? $firstResult['source_locale'] : ($this->options['source_locale'] ?? null),
                'target_locales' => is_array($firstResult) ? $firstResult['target_locales'] : ($this->options['target_locales'] ?? []),
                'attributes' => is_array($firstResult) ? $firstResult['translated_attributes'] : ($this->options['attributes'] ?? []),
                'force' => (bool) ($this->options['force'] ?? false),
                'results' => $results,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    protected function notificationsEnabled(): bool
    {
        try {
            return (bool) config('translatable.ai.notifications.enabled', true);
        } catch (Throwable) {
            return false;
        }
    }

    protected function resolveNotifier(Container $container): ?TranslationRequestNotifier
    {
        if ($container->bound(TranslationRequestNotifier::class)) {
            $notifier = $container->make(TranslationRequestNotifier::class);

            return $notifier instanceof TranslationRequestNotifier ? $notifier : null;
        }

        $notifierClass = config('translatable.ai.notifications.notifier');

        if (!is_string($notifierClass) || $notifierClass === '') {
            return null;
        }

        $notifier = $container->make($notifierClass);

        return $notifier instanceof TranslationRequestNotifier ? $notifier : null;
    }
}
