<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use PictaStudio\Translatable\Ai\Jobs\TranslateModelsJob;
use PictaStudio\Translatable\Http\RouteRequestAuthorizer;
use PictaStudio\Translatable\Tests\Models\{Post, Product};

use function Pest\Laravel\getJson;
use function Pest\Laravel\{postJson, withHeader};

beforeEach(function (): void {
    class_exists(Post::class);
    class_exists(Product::class);

    Relation::morphMap([
        'post' => Post::class,
        'product' => Product::class,
    ]);
});

it('queues selected models for translation through the api endpoint', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');
    Queue::fake();

    $firstPost = Post::query()->create([
        'slug' => 'about',
        'title:en' => 'About us',
        'summary:en' => 'We build multilingual websites.',
    ]);

    $secondPost = Post::query()->create([
        'slug' => 'contact',
        'title:en' => 'Contact',
        'summary:en' => 'Reach out to our team.',
    ]);

    withHeader('X-Translatable-Token', 'secret-token')
        ->postJson('/api/translatable/v1/translate', [
            'model' => Post::class,
            'ids' => [$firstPost->getKey(), $secondPost->getKey()],
            'source_locale' => 'en',
            'target_locales' => ['fr'],
        ])
        ->assertAccepted()
        ->assertJsonPath('meta.matched_models', 2)
        ->assertJsonPath('meta.queued', true)
        ->assertJsonPath('meta.queue', 'default');

    Queue::assertPushedOn('default', TranslateModelsJob::class);
    Queue::assertPushed(TranslateModelsJob::class, function (TranslateModelsJob $job) use ($firstPost, $secondPost): bool {
        return $job->requestedModel === Post::class
            && $job->modelClass === Post::class
            && $job->ids === [$firstPost->getKey(), $secondPost->getKey()]
            && $job->options['source_locale'] === 'en'
            && $job->options['target_locales'] === ['fr'];
    });
});

it('queues translation when resolving the model from the registered morph map', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');
    Queue::fake();

    $product = Product::query()->create([
        'name:en' => 'Desk',
        'stock' => 4,
    ]);

    withHeader('X-Translatable-Token', 'secret-token')
        ->postJson('/api/translatable/v1/translate', [
            'model' => 'product',
            'id' => $product->getKey(),
            'source_locale' => 'en',
            'target_locales' => ['it'],
        ])
        ->assertAccepted()
        ->assertJsonPath('meta.model', 'product')
        ->assertJsonPath('meta.model_class', Product::class)
        ->assertJsonPath('meta.requested_model', 'product')
        ->assertJsonPath('meta.matched_models', 1)
        ->assertJsonPath('meta.queued', true);

    Queue::assertPushed(TranslateModelsJob::class, function (TranslateModelsJob $job) use ($product): bool {
        return $job->requestedModel === 'product'
            && $job->modelClass === Product::class
            && $job->ids === [$product->getKey()]
            && $job->options['target_locales'] === ['it'];
    });
});

it('lists available translatable models and their fields', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');

    withHeader('X-Translatable-Token', 'secret-token')
        ->getJson('/api/translatable/v1/models')
        ->assertOk()
        ->assertJsonPath('meta.count', 2)
        ->assertJsonFragment([
            'model' => Post::class,
            'morph_alias' => 'post',
            'attributes' => ['title', 'summary'],
        ])
        ->assertJsonFragment([
            'model' => Product::class,
            'morph_alias' => 'product',
            'attributes' => ['name'],
        ]);
});

it('lists missing translations across all translatable models with pagination by default', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');

    $firstPost = Post::query()->create([
        'slug' => 'about',
        'title:en' => 'About us',
        'summary:en' => 'We build multilingual websites.',
    ]);

    $secondPost = Post::query()->create([
        'slug' => 'contact',
        'title:en' => 'Contact',
        'summary:en' => 'Reach out to our team.',
        'title:fr' => 'Contact',
    ]);

    $thirdPost = Post::query()->create([
        'slug' => 'pricing',
        'title:en' => 'Pricing',
        'summary:en' => 'Simple and predictable.',
        'title:it' => 'Prezzi',
        'summary:it' => 'Semplice e prevedibile.',
        'title:fr' => 'Tarifs',
        'summary:fr' => 'Simple et previsible.',
    ]);

    $product = Product::query()->create([
        'name' => 'Desk',
        'stock' => 4,
    ]);

    withHeader('X-Translatable-Token', 'secret-token')
        ->getJson('/api/translatable/v1/missing-translations?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.model', null)
        ->assertJsonPath('meta.model_class', null)
        ->assertJsonPath('meta.requested_model', null)
        ->assertJsonPath('meta.models.0', 'post')
        ->assertJsonPath('meta.models.1', 'product')
        ->assertJsonPath('meta.model_classes.0', Post::class)
        ->assertJsonPath('meta.model_classes.1', Product::class)
        ->assertJsonPath('meta.source_locale', 'en')
        ->assertJsonPath('meta.target_locales.0', 'it')
        ->assertJsonPath('meta.target_locales.1', 'fr')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.model_id', $firstPost->getKey())
        ->assertJsonPath('data.0.model_type', 'post')
        ->assertJsonPath('data.0.model_class', Post::class)
        ->assertJsonPath('data.0.missing.it.0', 'title')
        ->assertJsonPath('data.0.missing.it.1', 'summary')
        ->assertJsonPath('data.0.missing.fr.0', 'title')
        ->assertJsonPath('data.0.missing.fr.1', 'summary')
        ->assertJsonPath('data.0.missing_count', 4)
        ->assertJsonPath('data.0.source_values.title', 'About us')
        ->assertJsonPath('data.1.model_id', $secondPost->getKey())
        ->assertJsonPath('data.1.model_type', 'post')
        ->assertJsonPath('data.1.model_class', Post::class)
        ->assertJsonPath('data.1.missing.it.0', 'title')
        ->assertJsonPath('data.1.missing.it.1', 'summary')
        ->assertJsonPath('data.1.missing.fr.0', 'summary')
        ->assertJsonPath('data.1.missing_count', 3)
        ->assertJsonPath('data.1.source_values.summary', 'Reach out to our team.')
        ->assertJsonMissing([
            'model_id' => $thirdPost->getKey(),
        ]);

    withHeader('X-Translatable-Token', 'secret-token')
        ->getJson('/api/translatable/v1/missing-translations?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('data.0.model_id', $product->getKey())
        ->assertJsonPath('data.0.model_type', 'product')
        ->assertJsonPath('data.0.model_class', Product::class)
        ->assertJsonPath('data.0.missing.it.0', 'name')
        ->assertJsonPath('data.0.missing.fr.0', 'name')
        ->assertJsonPath('data.0.missing_count', 2)
        ->assertJsonPath('data.0.source_values.name', 'Desk');
});

it('filters missing translations by accepted state', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');

    $acceptedPost = Post::query()->create([
        'slug' => 'accepted',
        'title:en' => 'Accepted source',
        'summary:en' => 'Ready to translate.',
    ]);

    $aiPost = Post::query()->create([
        'slug' => 'ai-generated',
        'title:en' => 'Ignored base value',
    ]);
    $aiPost->setTranslationValue('en', 'title', 'AI source title', 'ai');
    $aiPost->setTranslationValue('en', 'summary', 'AI source summary', 'ai');
    $aiPost->save();
    $aiPost->refresh();

    withHeader('X-Translatable-Token', 'secret-token')
        ->getJson('/api/translatable/v1/missing-translations?accepted=true')
        ->assertOk()
        ->assertJsonPath('meta.accepted', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.model_id', $acceptedPost->getKey())
        ->assertJsonMissing([
            'model_id' => $aiPost->getKey(),
        ]);

    withHeader('X-Translatable-Token', 'secret-token')
        ->getJson('/api/translatable/v1/missing-translations?accepted=false')
        ->assertOk()
        ->assertJsonPath('meta.accepted', false)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.model_id', $aiPost->getKey())
        ->assertJsonMissing([
            'model_id' => $acceptedPost->getKey(),
        ]);
});

it('filters missing translations to only authorized models when no model is provided', function (): void {
    app(RouteRequestAuthorizer::class)->using(
        fn (Request $request, string $modelClass): bool => $modelClass === Product::class
    );

    Post::query()->create([
        'slug' => 'about',
        'title:en' => 'About us',
        'summary:en' => 'We build multilingual websites.',
    ]);

    $product = Product::query()->create([
        'name' => 'Desk',
        'stock' => 4,
    ]);

    getJson('/api/translatable/v1/missing-translations')
        ->assertOk()
        ->assertJsonPath('meta.model', 'product')
        ->assertJsonPath('meta.model_class', Product::class)
        ->assertJsonPath('meta.models.0', 'product')
        ->assertJsonPath('meta.model_classes.0', Product::class)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.model_type', 'product')
        ->assertJsonPath('data.0.model_class', Product::class)
        ->assertJsonPath('data.0.model_id', $product->getKey())
        ->assertJsonMissing([
            'model_type' => 'post',
        ]);
});

it('forbids the models endpoint when authorization blocks every model', function (): void {
    app(RouteRequestAuthorizer::class)->using(
        fn (Request $request, string $modelClass): bool => false
    );

    getJson('/api/translatable/v1/models')->assertForbidden();
});

it('filters the models endpoint to only authorized models', function (): void {
    app(RouteRequestAuthorizer::class)->using(
        fn (Request $request, string $modelClass): bool => $modelClass === Product::class
    );

    getJson('/api/translatable/v1/models')
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonFragment([
            'model' => Product::class,
            'morph_alias' => 'product',
            'attributes' => ['name'],
        ])
        ->assertJsonMissing([
            'model' => Post::class,
        ]);
});

it('rejects unauthorized api translation requests', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');

    $post = Post::query()->create([
        'slug' => 'about',
        'title:en' => 'About us',
        'summary:en' => 'We build multilingual websites.',
    ]);

    postJson('/api/translatable/v1/translate', [
        'model' => Post::class,
        'id' => $post->getKey(),
        'source_locale' => 'en',
        'target_locales' => ['fr'],
    ])->assertForbidden();
});

it('rejects unauthorized missing translations requests', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');

    getJson('/api/translatable/v1/missing-translations')
        ->assertForbidden();
});

it('allows the host application to provide custom authorization logic', function (): void {
    config()->set('translatable.routes.api.v1.authorization.token', 'secret-token');
    Queue::fake();

    app(RouteRequestAuthorizer::class)->using(
        fn (Request $request, string $modelClass): bool => $request->header('X-Allow-Translate') === 'yes'
            && $modelClass === Post::class
    );

    $post = Post::query()->create([
        'slug' => 'about',
        'title:en' => 'About us',
        'summary:en' => 'We build multilingual websites.',
    ]);

    withHeader('X-Allow-Translate', 'yes')
        ->postJson('/api/translatable/v1/translate', [
            'model' => Post::class,
            'id' => $post->getKey(),
            'source_locale' => 'en',
            'target_locales' => ['fr'],
        ])
        ->assertAccepted()
        ->assertJsonPath('meta.queued', true);

    Queue::assertPushed(TranslateModelsJob::class);
});
