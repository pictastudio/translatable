<?php

namespace PictaStudio\Translatable\Http;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouteRequestAuthorizer
{
    protected Closure|string|null $authorizer = null;

    public function __construct(
        protected Container $container,
    ) {}

    public function using(Closure|string|null $authorizer): self
    {
        $this->authorizer = $authorizer;

        return $this;
    }

    /**
     * @param  class-string  $modelClass
     */
    public function authorize(Request $request, string $modelClass): bool
    {
        $customAuthorizer = $this->resolveCustomAuthorizer();

        if ($customAuthorizer !== null) {
            return (bool) $this->container->call($customAuthorizer, [
                'request' => $request,
                'modelClass' => $modelClass,
            ]);
        }

        $token = $this->authorizationConfig('token');

        if (is_string($token) && $token !== '') {
            return $this->authorizeWithToken($request, $token);
        }

        $ability = $this->authorizationConfig('ability');

        if (is_string($ability) && $ability !== '') {
            return $this->authorizeWithGate($request, $ability, $modelClass);
        }

        return true;
    }

    public function isConfigured(): bool
    {
        return $this->resolveCustomAuthorizer() !== null
            || $this->hasConfiguredToken()
            || $this->hasConfiguredAbility();
    }

    protected function resolveCustomAuthorizer(): ?callable
    {
        $authorizer = $this->authorizer ?? $this->authorizationConfig('using');

        if (is_string($authorizer) && $authorizer !== '') {
            $authorizer = $this->container->make($authorizer);
        }

        return is_callable($authorizer) ? $authorizer : null;
    }

    protected function hasConfiguredToken(): bool
    {
        $token = $this->authorizationConfig('token');

        return is_string($token) && $token !== '';
    }

    protected function hasConfiguredAbility(): bool
    {
        $ability = $this->authorizationConfig('ability');

        return is_string($ability) && $ability !== '';
    }

    protected function authorizeWithToken(Request $request, string $token): bool
    {
        $header = $this->authorizationConfig('header', 'X-Translatable-Token');
        $providedToken = (string) $request->header($header, '');

        return $providedToken !== '' && hash_equals($token, $providedToken);
    }

    protected function authorizationConfig(string $key, mixed $default = null): mixed
    {
        return config("translatable.routes.api.v1.authorization.{$key}", $default);
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function authorizeWithGate(Request $request, string $ability, string $modelClass): bool
    {
        $user = $request->user();

        return $user !== null && Gate::forUser($user)->allows($ability, $modelClass);
    }
}
