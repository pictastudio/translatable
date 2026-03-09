<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use PictaStudio\Translatable\Ai\Jobs\TranslateModelsJob;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Http\Requests\V1\TranslateModelsRequest;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\Support\TranslatableModelRegistry;

class TranslateController extends Controller
{
    public function store(
        TranslateModelsRequest $request,
        RouteRequestAuthorizer $authorizer,
        TranslatableModelRegistry $registry,
    ): JsonResponse {
        $validated = $request->validated();
        $requestedModel = $validated['model'];
        $modelClass = $this->ensureTranslatableModelClass($requestedModel);
        $this->authorizeModelRequest($request, $modelClass, $authorizer);

        $ids = $this->resolveIds($validated);

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
                'notification_enabled' => (bool) config('translatable.ai.notifications.enabled', true),
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
}
