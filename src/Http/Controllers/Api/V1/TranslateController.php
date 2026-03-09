<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Http\Requests\V1\TranslateModelsRequest;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TranslateController extends Controller
{
    public function store(
        TranslateModelsRequest $request,
        ModelTranslator $translator,
        RouteRequestAuthorizer $authorizer,
    ): JsonResponse {
        $validated = $request->validated();
        $requestedModel = $validated['model'];
        $modelClass = $this->resolveModelClass($requestedModel);

        if (!$this->isTranslatableModelClass($modelClass)) {
            throw ValidationException::withMessages([
                'model' => "The model [{$requestedModel}] must extend Eloquent and implement the translatable contract.",
            ]);
        }

        $this->authorizeRequest($request, $modelClass, $authorizer);

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

        $results = $translator->translateMany($models->all(), [
            'source_locale' => $validated['source_locale'] ?? null,
            'target_locales' => $validated['target_locales'] ?? null,
            'attributes' => $validated['attributes'] ?? null,
            'force' => (bool) ($validated['force'] ?? false),
            'provider' => $validated['provider'] ?? null,
            'model' => $validated['model_name'] ?? null,
        ]);
        $translatedPairs = array_sum(array_column($results, 'translated_count'));

        return response()->json([
            'data' => $results,
            'meta' => [
                'model' => $modelClass,
                'requested_model' => $requestedModel,
                'requested_ids' => $ids,
                'matched_models' => count($results),
                'translated_pairs' => $translatedPairs,
            ],
        ]);
    }

    protected function isTranslatableModelClass(mixed $modelClass): bool
    {
        return is_string($modelClass)
            && class_exists($modelClass)
            && is_subclass_of($modelClass, Model::class)
            && is_subclass_of($modelClass, TranslatableContract::class);
    }

    protected function resolveModelClass(string $model): string
    {
        $morphedModel = Relation::getMorphedModel($model);

        if (is_string($morphedModel) && $morphedModel !== '') {
            return $morphedModel;
        }

        return $model;
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
     * @param  class-string<Model>  $modelClass
     */
    protected function authorizeRequest(
        TranslateModelsRequest $request,
        string $modelClass,
        RouteRequestAuthorizer $authorizer,
    ): void {
        if ($authorizer->isConfigured() && !$authorizer->authorize($request, $modelClass)) {
            throw new AccessDeniedHttpException('Unauthorized translation request.');
        }
    }
}
