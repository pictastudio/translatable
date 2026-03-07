<?php

namespace PictaStudio\Translatable\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Validation\ValidationException;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;

class TranslateModelsController
{
    public function __invoke(Request $request, ModelTranslator $translator): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'id' => ['nullable'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['nullable'],
            'source_locale' => ['nullable', 'string'],
            'target_locales' => ['nullable', 'array'],
            'target_locales.*' => ['required', 'string'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['required', 'string'],
            'force' => ['nullable', 'boolean'],
            'provider' => ['nullable', 'string'],
            'model_name' => ['nullable', 'string'],
        ]);

        $modelClass = $validated['model'];

        if (!$this->isTranslatableModelClass($modelClass)) {
            throw ValidationException::withMessages([
                'model' => "The model [{$modelClass}] must extend Eloquent and implement the translatable contract.",
            ]);
        }

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

        $results = [];
        $translatedPairs = 0;

        foreach ($models as $model) {
            $summary = $translator->translate($model, [
                'source_locale' => $validated['source_locale'] ?? null,
                'target_locales' => $validated['target_locales'] ?? null,
                'attributes' => $validated['attributes'] ?? null,
                'force' => (bool) ($validated['force'] ?? false),
                'provider' => $validated['provider'] ?? null,
                'model' => $validated['model_name'] ?? null,
            ]);

            $translatedPairs += $summary['translated_count'];
            $results[] = $summary;
        }

        return response()->json([
            'data' => $results,
            'meta' => [
                'model' => $modelClass,
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
