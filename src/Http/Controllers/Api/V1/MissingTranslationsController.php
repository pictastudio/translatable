<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Http\Requests\V1\ListMissingTranslationsRequest;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\Support\MissingTranslations;
use PictaStudio\Translatable\Support\TranslatableModelRegistry;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MissingTranslationsController extends Controller
{
    public function index(
        ListMissingTranslationsRequest $request,
        MissingTranslations $missingTranslations,
        RouteRequestAuthorizer $authorizer,
        TranslatableModelRegistry $registry,
    ): JsonResponse {
        $validated = $request->validated();
        $requestedModel = $validated['model'] ?? null;
        $modelClasses = $this->resolveAuthorizedModelClasses(
            $request,
            $requestedModel,
            $missingTranslations,
            $authorizer,
        );
        $results = $missingTranslations->paginate($modelClasses, $validated);
        $paginator = $results['paginator'];

        return response()->json([
            'data' => $results['data'],
            'meta' => [
                'model' => count($modelClasses) === 1 ? $registry->aliasFor($modelClasses[0]) : null,
                'model_class' => count($modelClasses) === 1 ? $modelClasses[0] : null,
                'requested_model' => $requestedModel,
                'models' => array_map(fn (string $modelClass): string => $registry->aliasFor($modelClass), $modelClasses),
                'model_classes' => $modelClasses,
                'source_locale' => $results['source_locale'],
                'target_locales' => $results['target_locales'],
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url(max($paginator->lastPage(), 1)),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @return array<int, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected function resolveAuthorizedModelClasses(
        Request $request,
        ?string $requestedModel,
        MissingTranslations $missingTranslations,
        RouteRequestAuthorizer $authorizer,
    ): array {
        if (is_string($requestedModel) && $requestedModel !== '') {
            $modelClass = $this->ensureTranslatableModelClass($requestedModel);
            $this->authorizeModelRequest($request, $modelClass, $authorizer);

            return [$modelClass];
        }

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
}
