<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PictaStudio\Translatable\Ai\Jobs\TranslateModelsJob;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Http\Requests\V1\TranslateModelsRequest;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\Support\{MissingTranslations, TranslatableModelRegistry};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TranslateController extends Controller
{
    public function store(
        TranslateModelsRequest $request,
        RouteRequestAuthorizer $authorizer,
        TranslatableModelRegistry $registry,
        MissingTranslations $missingTranslations,
    ): JsonResponse {
        $validated = $request->validated();
        $requestedModel = $validated['model'] ?? null;
        $ids = $this->resolveIds($validated);

        if (!is_string($requestedModel) || $requestedModel === '') {
            if ($ids !== []) {
                throw ValidationException::withMessages([
                    'model' => 'The model field is required when model ids are provided.',
                ]);
            }

            return $this->storeMissingTranslations(
                $request,
                $validated,
                $authorizer,
                $registry,
                $missingTranslations,
            );
        }

        $modelClass = $this->ensureTranslatableModelClass($requestedModel);
        $this->authorizeModelRequest($request, $modelClass, $authorizer);

        if ($ids === []) {
            throw ValidationException::withMessages([
                'ids' => 'At least one model id must be provided.',
            ]);
        }

        /** @var class-string<Model&TranslatableContract> $modelClass */
        $models = $modelClass::query()->whereKey($ids)->get();

        if ($models->isEmpty()) {
            return response()->json([
                'message' => 'No matching translatable models were found.',
            ], 404);
        }

        $options = [
            'source_locale' => $validated['source_locale'] ?? null,
            'target_locales' => $validated['target_locales'] ?? null,
            'attributes' => $validated['attributes'] ?? null,
            'force' => (bool) ($validated['force'] ?? false),
            'provider' => $validated['provider'] ?? null,
            'model' => $validated['model_name'] ?? null,
        ];

        $user = $request->user();

        TranslateModelsJob::dispatch(
            requestedModel: $requestedModel,
            modelClass: $modelClass,
            ids: $ids,
            options: $options,
            notifiable: is_object($user) ? $user : null,
        );

        return response()->json([
            'meta' => [
                'model' => $registry->aliasFor($modelClass),
                'model_class' => $modelClass,
                'requested_model' => $requestedModel,
                'requested_ids' => $ids,
                'matched_models' => $models->count(),
                'queued' => true,
                'queue' => config('translatable.ai.queue.name', 'default'),
            ],
        ], 202);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function storeMissingTranslations(
        Request $request,
        array $validated,
        RouteRequestAuthorizer $authorizer,
        TranslatableModelRegistry $registry,
        MissingTranslations $missingTranslations,
    ): JsonResponse {
        $modelClasses = $this->resolveAuthorizedModelClasses($request, $missingTranslations, $authorizer);
        $results = $missingTranslations->collect($modelClasses, [
            'source_locale' => $validated['source_locale'] ?? null,
            'target_locales' => $validated['target_locales'] ?? null,
            'accepted' => true,
        ]);
        $requestedAttributes = $this->resolveRequestedAttributes($validated);

        $jobs = collect($results['data'])
            ->groupBy('model_class')
            ->map(function (Collection $rows, string $modelClass) use ($requestedAttributes): ?array {
                $attributes = $rows
                    ->pluck('translated_attributes')
                    ->flatten()
                    ->filter(fn (mixed $attribute): bool => is_string($attribute) && $attribute !== '')
                    ->when(
                        $requestedAttributes !== [],
                        fn (Collection $attributes): Collection => $attributes->intersect($requestedAttributes)
                    )
                    ->unique()
                    ->values()
                    ->all();

                if ($attributes === []) {
                    return null;
                }

                return [
                    'requested_model' => $modelClass,
                    'model_class' => $modelClass,
                    'ids' => $rows->pluck('model_id')->unique()->values()->all(),
                    'attributes' => $attributes,
                ];
            })
            ->filter()
            ->values();

        if ($jobs->isEmpty()) {
            return response()->json([
                'message' => 'No missing translations were found.',
            ], 404);
        }

        $options = [
            'source_locale' => $results['source_locale'],
            'target_locales' => $results['target_locales'],
            'force' => false,
            'provider' => $validated['provider'] ?? null,
            'model' => $validated['model_name'] ?? null,
        ];
        $queuedModelClasses = $jobs->pluck('model_class')->all();

        $user = $request->user();

        $jobs->each(function (array $job) use ($options, $user): void {
            TranslateModelsJob::dispatch(
                requestedModel: $job['requested_model'],
                modelClass: $job['model_class'],
                ids: $job['ids'],
                options: [...$options, 'attributes' => $job['attributes']],
                notifiable: is_object($user) ? $user : null,
            );
        });

        return response()->json([
            'meta' => [
                'model' => count($queuedModelClasses) === 1 ? $registry->aliasFor($queuedModelClasses[0]) : null,
                'model_class' => count($queuedModelClasses) === 1 ? $queuedModelClasses[0] : null,
                'requested_model' => null,
                'requested_ids' => [],
                'models' => array_map(fn (string $modelClass): string => $registry->aliasFor($modelClass), $queuedModelClasses),
                'model_classes' => $queuedModelClasses,
                'matched_models' => $jobs->sum(fn (array $job): int => count($job['ids'])),
                'queued_jobs' => $jobs->count(),
                'queued' => true,
                'queue' => config('translatable.ai.queue.name', 'default'),
            ],
        ], 202);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int|string>
     */
    protected function resolveIds(array $validated): array
    {
        $ids = [];

        if (array_key_exists('id', $validated) && $validated['id'] !== null && $validated['id'] !== '') {
            $ids[] = $validated['id'];
        }

        foreach (($validated['ids'] ?? []) as $id) {
            if ($id !== null && $id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids, SORT_REGULAR));
    }

    /**
     * @return array<int, class-string<Model&TranslatableContract>>
     */
    protected function resolveAuthorizedModelClasses(
        Request $request,
        MissingTranslations $missingTranslations,
        RouteRequestAuthorizer $authorizer,
    ): array {
        $modelClasses = array_values(array_filter(
            $missingTranslations->allModelClasses(),
            fn (string $modelClass): bool => !$authorizer->isConfigured()
                || $authorizer->authorize($request, $modelClass)
        ));

        if ($authorizer->isConfigured() && $modelClasses === []) {
            throw new AccessDeniedHttpException('Unauthorized translation request.');
        }

        return $modelClasses;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, string>
     */
    protected function resolveRequestedAttributes(array $validated): array
    {
        return collect($validated['attributes'] ?? [])
            ->filter(fn (mixed $attribute): bool => is_string($attribute) && $attribute !== '')
            ->unique()
            ->values()
            ->all();
    }
}
