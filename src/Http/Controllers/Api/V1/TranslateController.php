<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Http\Requests\V1\TranslateModelsRequest;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;

class TranslateController extends Controller
{
    public function store(
        TranslateModelsRequest $request,
        ModelTranslator $translator,
        RouteRequestAuthorizer $authorizer,
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
