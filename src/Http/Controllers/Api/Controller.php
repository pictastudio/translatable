<?php

namespace PictaStudio\Translatable\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

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

    protected function ensureTranslatableModelClass(string $requestedModel): string
    {
        $modelClass = $this->resolveModelClass($requestedModel);

        if (!$this->isTranslatableModelClass($modelClass)) {
            throw ValidationException::withMessages([
                'model' => "The model [{$requestedModel}] must extend Eloquent and implement the translatable contract.",
            ]);
        }

        return $modelClass;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function authorizeModelRequest(
        Request $request,
        string $modelClass,
        RouteRequestAuthorizer $authorizer,
    ): void {
        if ($authorizer->isConfigured() && !$authorizer->authorize($request, $modelClass)) {
            throw new AccessDeniedHttpException('Unauthorized translation request.');
        }
    }
}
