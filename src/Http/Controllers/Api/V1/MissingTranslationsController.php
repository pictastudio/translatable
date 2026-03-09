<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Http\Requests\V1\ListMissingTranslationsRequest;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\Support\MissingTranslations;

class MissingTranslationsController extends Controller
{
    public function index(
        ListMissingTranslationsRequest $request,
        MissingTranslations $missingTranslations,
        RouteRequestAuthorizer $authorizer,
    ): JsonResponse {
        $validated = $request->validated();
        $requestedModel = $validated['model'];
        $modelClass = $this->ensureTranslatableModelClass($requestedModel);

        $this->authorizeModelRequest($request, $modelClass, $authorizer);

        $results = $missingTranslations->paginate($modelClass, $validated);
        $paginator = $results['paginator'];

        return response()->json([
            'data' => $results['data'],
            'meta' => [
                'model' => $modelClass,
                'requested_model' => $requestedModel,
                'source_locale' => $results['source_locale'],
                'target_locales' => $results['target_locales'],
                'attributes' => $results['attributes'],
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
}
