<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
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

it('translates selected models through the api endpoint in a single batch', function (): void {
    config()->set('translatable.ai.routes.authorization.token', 'secret-token');

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

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['model_id' => (string) $firstPost->getKey(), 'locale' => 'fr', 'attribute' => 'title', 'value' => 'A propos de nous'],
                ['model_id' => (string) $firstPost->getKey(), 'locale' => 'fr', 'attribute' => 'summary', 'value' => 'Nous construisons des sites web multilingues.'],
                ['model_id' => (string) $secondPost->getKey(), 'locale' => 'fr', 'attribute' => 'title', 'value' => 'Contact'],
                ['model_id' => (string) $secondPost->getKey(), 'locale' => 'fr', 'attribute' => 'summary', 'value' => 'Contactez notre equipe.'],
            ],
        ],
    ])->preventStrayPrompts();

    withHeader('X-Translatable-Token', 'secret-token')
        ->postJson('/api/translatable/v1/translate', [
            'model' => Post::class,
            'ids' => [$firstPost->getKey(), $secondPost->getKey()],
            'source_locale' => 'en',
            'target_locales' => ['fr'],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.model_type', 'post')
        ->assertJsonPath('data.0.model_class', Post::class)
        ->assertJsonPath('data.0.translated.fr.title', 'A propos de nous')
        ->assertJsonPath('data.1.translated.fr.summary', 'Contactez notre equipe.')
        ->assertJsonPath('meta.matched_models', 2)
        ->assertJsonPath('meta.translated_pairs', 4);

    $firstPost->refresh();
    $secondPost->refresh();

    expect($firstPost->{'title:fr'})->toBe('A propos de nous');
    expect($firstPost->{'summary:fr'})->toBe('Nous construisons des sites web multilingues.');
    expect($secondPost->{'title:fr'})->toBe('Contact');
    expect($secondPost->{'summary:fr'})->toBe('Contactez notre equipe.');
});

it('resolves the model from the registered morph map', function (): void {
    config()->set('translatable.ai.routes.authorization.token', 'secret-token');

    $product = Product::query()->create([
        'name:en' => 'Desk',
        'stock' => 4,
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['model_id' => (string) $product->getKey(), 'locale' => 'it', 'attribute' => 'name', 'value' => 'Scrivania'],
            ],
        ],
    ])->preventStrayPrompts();

    withHeader('X-Translatable-Token', 'secret-token')
        ->postJson('/api/translatable/v1/translate', [
            'model' => 'product',
            'id' => $product->getKey(),
            'source_locale' => 'en',
            'target_locales' => ['it'],
        ])
        ->assertOk()
        ->assertJsonPath('meta.model', 'product')
        ->assertJsonPath('meta.model_class', Product::class)
        ->assertJsonPath('meta.requested_model', 'product')
        ->assertJsonPath('data.0.model_type', 'product')
        ->assertJsonPath('data.0.model_class', Product::class)
        ->assertJsonPath('data.0.translated.it.name', 'Scrivania');
});

it('lists available translatable models and their fields', function (): void {
    config()->set('translatable.ai.routes.authorization.token', 'secret-token');

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
    config()->set('translatable.ai.routes.authorization.token', 'secret-token');

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
    config()->set('translatable.ai.routes.authorization.token', 'secret-token');

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
    config()->set('translatable.ai.routes.authorization.token', 'secret-token');

    getJson('/api/translatable/v1/missing-translations')
        ->assertForbidden();
});

it('allows the host application to provide custom authorization logic', function (): void {
    config()->set('translatable.ai.routes.authorization.token', 'secret-token');

    app(RouteRequestAuthorizer::class)->using(
        fn (Request $request, string $modelClass): bool => $request->header('X-Allow-Translate') === 'yes'
            && $modelClass === Post::class
    );

    $post = Post::query()->create([
        'slug' => 'about',
        'title:en' => 'About us',
        'summary:en' => 'We build multilingual websites.',
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'title', 'value' => 'A propos de nous'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'summary', 'value' => 'Nous construisons des sites web multilingues.'],
            ],
        ],
    ])->preventStrayPrompts();

    withHeader('X-Allow-Translate', 'yes')
        ->postJson('/api/translatable/v1/translate', [
            'model' => Post::class,
            'id' => $post->getKey(),
            'source_locale' => 'en',
            'target_locales' => ['fr'],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.translated.fr.title', 'A propos de nous');
});
