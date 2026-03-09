<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
use PictaStudio\Translatable\Ai\Jobs\TranslateModelsJob;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Events\AiTranslationsCompleted;
use PictaStudio\Translatable\Tests\Models\{Post, Product};

beforeEach(function (): void {
    class_exists(Post::class);
    class_exists(Product::class);

    Relation::morphMap([
        'post' => Post::class,
        'product' => Product::class,
    ]);
});

it('defaults the queued job to the default queue', function (): void {
    $job = new TranslateModelsJob(
        requestedModel: 'post',
        modelClass: Post::class,
        ids: [1],
    );

    expect($job->queue)->toBe('default');
});

it('translates models in the queued job and dispatches a completion event', function (): void {
    Event::fake([AiTranslationsCompleted::class]);
    $user = new TestNotifiable('translator@example.com');

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

    $job = new TranslateModelsJob(
        requestedModel: 'post',
        modelClass: Post::class,
        ids: [$post->getKey()],
        options: [
            'source_locale' => 'en',
            'target_locales' => ['fr'],
        ],
        notifiable: $user,
    );

    $job->handle(
        app(ModelTranslator::class),
    );

    $post->refresh();

    expect($post->{'title:fr'})->toBe('A propos de nous')
        ->and($post->{'summary:fr'})->toBe('Nous construisons des sites web multilingues.');

    Event::assertDispatched(
        AiTranslationsCompleted::class,
        function (AiTranslationsCompleted $event) use ($user): bool {
            return $event->notifiable === $user
                && $event->summary['model'] === 'post'
                && $event->summary['matched_models'] === 1
                && $event->summary['translated_pairs'] === 2
                && $event->summary['target_locales'] === ['fr'];
        }
    );
});

it('dispatches the completion event even without a notifiable model', function (): void {
    Event::fake([AiTranslationsCompleted::class]);

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

    $job = new TranslateModelsJob(
        requestedModel: 'post',
        modelClass: Post::class,
        ids: [$post->getKey()],
        options: [
            'source_locale' => 'en',
            'target_locales' => ['fr'],
        ],
    );

    $job->handle(
        app(ModelTranslator::class),
    );

    Event::assertDispatched(
        AiTranslationsCompleted::class,
        fn (AiTranslationsCompleted $event): bool => $event->notifiable === null
            && $event->summary['model'] === 'post'
            && $event->summary['translated_pairs'] === 2
    );
});

it('keeps translations working when event listeners fail', function (): void {
    Event::listen(AiTranslationsCompleted::class, static function (): void {
        throw new RuntimeException('listener failed');
    });

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

    $job = new TranslateModelsJob(
        requestedModel: 'post',
        modelClass: Post::class,
        ids: [$post->getKey()],
        options: [
            'source_locale' => 'en',
            'target_locales' => ['fr'],
        ],
        notifiable: new TestNotifiable('translator@example.com'),
    );

    $job->handle(app(ModelTranslator::class));

    $post->refresh();

    expect($post->{'title:fr'})->toBe('A propos de nous')
        ->and($post->{'summary:fr'})->toBe('Nous construisons des sites web multilingues.');
});

class TestNotifiable
{
    public function __construct(
        public string $email,
    ) {}

    public function getKey(): string
    {
        return $this->email;
    }
}
