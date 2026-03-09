<?php

it('merges nested config recursively while preserving package defaults', function () {
    expect(config('translatable.routes.api.enable'))->toBeTrue()
        ->and(config('translatable.routes.api.v1.middleware'))->toBe([])
        ->and(config('translatable.routes.api.v1.prefix'))->toBe('api/translatable/v1')
        ->and(config('translatable.routes.api.v1.name'))->toBe('api.translatable.v1')
        ->and(config('translatable.routes.api.v1.authorization.header'))->toBe('X-Translatable-Token')
        ->and(config('translatable.ai.routes.authorization.header'))->toBe('X-Translatable-Token');
});
