<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Http\{JsonResponse, Request};
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\Support\TranslatableModelRegistry;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TranslatableModelsController extends Controller
{
    public function index(
        Request $request,
        TranslatableModelRegistry $registry,
        RouteRequestAuthorizer $authorizer,
    ): JsonResponse {
        $models = collect($registry->descriptors())
            ->filter(function (array $descriptor) use ($request, $authorizer): bool {
                return !$authorizer->isConfigured()
                    || $authorizer->authorize($request, $descriptor['model']);
            })
            ->values();

        if ($authorizer->isConfigured() && $models->isEmpty()) {
            throw new AccessDeniedHttpException('Unauthorized translation request.');
        }

        return response()->json([
            'data' => $models->all(),
            'meta' => [
                'count' => $models->count(),
            ],
        ]);
    }
}
