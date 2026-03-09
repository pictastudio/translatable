<?php

use Illuminate\Console\Scheduling\Schedule;
use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
use PictaStudio\Translatable\Tests\Models\{Post, Product};

use function Pest\Laravel\artisan;

it('translates models from the artisan command', function (): void {
    $firstPost = Post::query()->create([
        'slug' => 'pricing',
        'title:en' => 'Pricing',
        'summary:en' => 'Choose the right plan for your team.',
        'title:it' => 'Prezzi vecchi',
    ]);

    $secondPost = Post::query()->create([
        'slug' => 'features',
        'title:en' => 'Features',
        'summary:en' => 'Everything your team needs in one place.',
        'title:it' => 'Funzionalita vecchie',
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['model_id' => (string) $firstPost->getKey(), 'locale' => 'it', 'attribute' => 'title', 'value' => 'Prezzi aggiornati'],
                ['model_id' => (string) $firstPost->getKey(), 'locale' => 'it', 'attribute' => 'summary', 'value' => 'Scegli il piano giusto per il tuo team.'],
                ['model_id' => (string) $secondPost->getKey(), 'locale' => 'it', 'attribute' => 'title', 'value' => 'Funzionalita aggiornate'],
                ['model_id' => (string) $secondPost->getKey(), 'locale' => 'it', 'attribute' => 'summary', 'value' => 'Tutto cio di cui il tuo team ha bisogno in un solo posto.'],
            ],
        ],
    ])->preventStrayPrompts();

    artisan('translatable:translate', [
        'model' => Post::class,
        '--ids' => [$firstPost->getKey(), $secondPost->getKey()],
        '--source-locale' => 'en',
        '--target-locales' => ['it'],
        '--attributes' => ['title', 'summary'],
        '--force' => true,
    ])->assertSuccessful();

    $firstPost->refresh();
    $secondPost->refresh();

    expect($firstPost->{'title:it'})->toBe('Prezzi aggiornati');
    expect($firstPost->{'summary:it'})->toBe('Scegli il piano giusto per il tuo team.');
    expect($secondPost->{'title:it'})->toBe('Funzionalita aggiornate');
    expect($secondPost->{'summary:it'})->toBe('Tutto cio di cui il tuo team ha bisogno in un solo posto.');
});

it('prompts for the model and defaults the source locale to the current app locale', function (): void {
    app()->setLocale('it');

    class_exists(Post::class);
    class_exists(Product::class);

    $post = Post::query()->create([
        'slug' => 'chi-siamo',
        'title:it' => 'Chi siamo',
        'summary:it' => 'Realizziamo siti web multilingua.',
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'title', 'value' => 'Qui nous sommes'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'summary', 'value' => 'Nous realisons des sites web multilingues.'],
            ],
        ],
    ])->preventStrayPrompts();

    artisan('translatable:translate', [
        '--ids' => [$post->getKey()],
    ])
        ->expectsSearch(
            'Which model would you like to translate?',
            Post::class,
            'Post',
            [Post::class]
        )
        ->expectsChoice(
            'Which locales would you like to translate into?',
            ['fr'],
            ['en', 'fr']
        )
        ->expectsChoice(
            'Which attributes would you like to translate?',
            ['title', 'summary'],
            ['title', 'summary']
        )
        ->expectsConfirmation('Overwrite existing translations?', 'no')
        ->assertSuccessful();

    $post->refresh();

    expect($post->{'title:fr'})->toBe('Qui nous sommes');
    expect($post->{'summary:fr'})->toBe('Nous realisons des sites web multilingues.');

    TranslateModelAgent::assertPrompted(function ($prompt): bool {
        return $prompt->contains('"source_locale": "it"')
            && $prompt->contains('Chi siamo')
            && $prompt->contains('Realizziamo siti web multilingua.');
    });
});

it('translates missing translations across all translatable models from the dedicated command', function (): void {
    $post = Post::query()->create([
        'slug' => 'pricing',
        'title:en' => 'Pricing',
        'summary:en' => 'Choose the right plan for your team.',
    ]);

    $product = Product::query()->create([
        'name:en' => 'Chair',
        'stock' => 4,
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['model_id' => (string) $post->getKey(), 'locale' => 'it', 'attribute' => 'title', 'value' => 'Prezzi'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'it', 'attribute' => 'summary', 'value' => 'Scegli il piano giusto per il tuo team.'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'title', 'value' => 'Tarifs'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'summary', 'value' => 'Choisissez le bon plan pour votre equipe.'],
            ],
        ],
        [
            'translations' => [
                ['model_id' => (string) $product->getKey(), 'locale' => 'it', 'attribute' => 'name', 'value' => 'Sedia'],
                ['model_id' => (string) $product->getKey(), 'locale' => 'fr', 'attribute' => 'name', 'value' => 'Chaise'],
            ],
        ],
    ])->preventStrayPrompts();

    artisan('translatable:translate-missing', [
        '--source-locale' => 'en',
        '--target-locales' => ['it'],
    ])->assertSuccessful();

    $post->refresh();
    $product->refresh();

    expect($post->{'title:it'})->toBe('Prezzi');
    expect($post->{'summary:it'})->toBe('Scegli il piano giusto per il tuo team.');
    expect($product->{'name:it'})->toBe('Sedia');
});

it('defaults the missing translation command to the current app locale and all configured locales', function (): void {
    app()->setLocale('en');

    $post = Post::query()->create([
        'slug' => 'pricing',
        'title:en' => 'Pricing',
        'summary:en' => 'Choose the right plan for your team.',
    ]);

    $product = Product::query()->create([
        'name:en' => 'Chair',
        'stock' => 4,
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['model_id' => (string) $post->getKey(), 'locale' => 'it', 'attribute' => 'title', 'value' => 'Prezzi'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'it', 'attribute' => 'summary', 'value' => 'Scegli il piano giusto per il tuo team.'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'title', 'value' => 'Tarifs'],
                ['model_id' => (string) $post->getKey(), 'locale' => 'fr', 'attribute' => 'summary', 'value' => 'Choisissez le bon plan pour votre equipe.'],
            ],
        ],
        [
            'translations' => [
                ['model_id' => (string) $product->getKey(), 'locale' => 'it', 'attribute' => 'name', 'value' => 'Sedia'],
                ['model_id' => (string) $product->getKey(), 'locale' => 'fr', 'attribute' => 'name', 'value' => 'Chaise'],
            ],
        ],
    ])->preventStrayPrompts();

    artisan('translatable:translate-missing')->assertSuccessful();

    $post->refresh();
    $product->refresh();

    expect($post->{'title:it'})->toBe('Prezzi');
    expect($post->{'summary:it'})->toBe('Scegli il piano giusto per il tuo team.');
    expect($post->{'title:fr'})->toBe('Tarifs');
    expect($post->{'summary:fr'})->toBe('Choisissez le bon plan pour votre equipe.');
    expect($product->{'name:it'})->toBe('Sedia');
    expect($product->{'name:fr'})->toBe('Chaise');

    TranslateModelAgent::assertPrompted(function ($prompt): bool {
        return $prompt->contains('"source_locale": "en"');
    });
});

it('registers the dedicated missing translation command schedule from config', function (): void {
    config()->set('translatable.commands.translate_missing.enabled', true);
    config()->set('translatable.commands.translate_missing.schedule', '15 2 * * *');

    $schedule = app(Schedule::class);

    expect(collect($schedule->events())->contains(function ($event): bool {
        return str_contains($event->command, 'translatable:translate-missing')
            && $event->expression === '15 2 * * *';
    }))->toBeTrue();
});
