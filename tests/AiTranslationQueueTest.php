<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Notification;
use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
use PictaStudio\Translatable\Ai\Jobs\TranslateModelsJob;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Contracts\TranslationRequestNotifier;
use PictaStudio\Translatable\Notifications\TranslationCompletedNotification;
use PictaStudio\Translatable\Tests\Models\{Post, Product};

beforeEach(function (): void {
    class_exists(Post::class);
    class_exists(Product::class);

    Relation::morphMap([
        'post' => Post::class,
        'product' => Product::class,
    ]);

    TestTranslationRequestNotifier::$summaries = [];
});

it('defaults the queued job to the default queue', function (): void {
    $job = new TranslateModelsJob(
        requestedModel: 'post',
        modelClass: Post::class,
        ids: [1],
    );

    expect($job->queue)->toBe('default');
});

it('translates models in the queued job and notifies the requesting user', function (): void {
    Notification::fake();

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

    Notification::assertSentTo(
        $user,
        TranslationCompletedNotification::class,
        function (TranslationCompletedNotification $notification, array $channels): bool {
            $payload = $notification->toArray(new TestNotifiable('translator@example.com'));

            return $channels === ['mail', 'database']
                && $payload['model'] === 'post'
                && $payload['matched_models'] === 1
                && $payload['translated_pairs'] === 2
                && $payload['target_locales'] === ['fr'];
        }
    );
});

it('can disable completion notifications from config', function (): void {
    config()->set('translatable.ai.notifications.enabled', false);
    Notification::fake();

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

    Notification::assertNothingSent();
});

it('can swap the completion notifier from config', function (): void {
    config()->set('translatable.ai.notifications.notifier', TestTranslationRequestNotifier::class);

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

    expect(TestTranslationRequestNotifier::$summaries)->toHaveCount(1)
        ->and(TestTranslationRequestNotifier::$summaries[0]['model'])->toBe('post')
        ->and(TestTranslationRequestNotifier::$summaries[0]['translated_pairs'])->toBe(2);
});

it('keeps translations working when notifier resolution fails', function (): void {
    config()->set('translatable.ai.notifications.notifier', 'Missing\\Notifier');

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

class TestTranslationRequestNotifier implements TranslationRequestNotifier
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public static array $summaries = [];

    public function notify(object $notifiable, array $summary): void
    {
        self::$summaries[] = $summary;
    }
}

class TestNotifiable
{
    public function __construct(
        public string $email,
    ) {}

    public function getKey(): string
    {
        return $this->email;
    }

    public function routeNotificationForMail(object $notification): string
    {
        return $this->email;
    }
}
