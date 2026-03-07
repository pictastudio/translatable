<?php

use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
use PictaStudio\Translatable\Tests\Models\{Post, Product};

use function Pest\Laravel\artisan;

it('translates models from the artisan command', function (): void {
    $post = Post::query()->create([
        'slug' => 'pricing',
        'title:en' => 'Pricing',
        'summary:en' => 'Choose the right plan for your team.',
        'title:it' => 'Prezzi vecchi',
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['locale' => 'it', 'attribute' => 'title', 'value' => 'Prezzi aggiornati'],
                ['locale' => 'it', 'attribute' => 'summary', 'value' => 'Scegli il piano giusto per il tuo team.'],
            ],
        ],
    ])->preventStrayPrompts();

    artisan('translatable:translate', [
        'model' => Post::class,
        '--ids' => [$post->getKey()],
        '--source-locale' => 'en',
        '--target-locales' => ['it'],
        '--attributes' => ['title', 'summary'],
        '--force' => true,
    ])->assertSuccessful();

    $post->refresh();

    expect($post->{'title:it'})->toBe('Prezzi aggiornati');
    expect($post->{'summary:it'})->toBe('Scegli il piano giusto per il tuo team.');
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
                ['locale' => 'fr', 'attribute' => 'title', 'value' => 'Qui nous sommes'],
                ['locale' => 'fr', 'attribute' => 'summary', 'value' => 'Nous realisons des sites web multilingues.'],
            ],
        ],
    ])->preventStrayPrompts();

    artisan('translatable:translate', [
        '--ids' => [$post->getKey()],
    ])
        ->expectsChoice(
            'Which model would you like to translate?',
            Post::class,
            [Post::class, Product::class]
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
