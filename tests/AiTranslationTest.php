<?php

use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
use PictaStudio\Translatable\Ai\ModelTranslator;
use PictaStudio\Translatable\Tests\Models\Post;

it('translates missing model fields with ai without overwriting existing translations by default', function (): void {
    $post = Post::query()->create([
        'slug' => 'welcome',
        'title:en' => 'Welcome',
        'summary:en' => 'A long body that should be translated.',
        'title:it' => 'Titolo esistente',
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['locale' => 'it', 'attribute' => 'summary', 'value' => 'Un testo lungo da tradurre.'],
                ['locale' => 'fr', 'attribute' => 'title', 'value' => 'Bienvenue'],
                ['locale' => 'fr', 'attribute' => 'summary', 'value' => 'Un long texte a traduire.'],
            ],
        ],
    ])->preventStrayPrompts();

    $summary = app(ModelTranslator::class)->translate($post, [
        'source_locale' => 'en',
        'target_locales' => ['it', 'fr'],
    ]);

    $post->refresh();

    expect($summary['requested_count'])->toBe(3);
    expect($summary['translated_count'])->toBe(3);
    expect($summary['skipped'])->toContain([
        'locale' => 'it',
        'attribute' => 'title',
        'reason' => 'existing_translation',
    ]);
    expect($post->{'title:it'})->toBe('Titolo esistente');
    expect($post->{'summary:it'})->toBe('Un testo lungo da tradurre.');
    expect($post->{'title:fr'})->toBe('Bienvenue');
    expect($post->{'summary:fr'})->toBe('Un long texte a traduire.');

    TranslateModelAgent::assertPrompted(function ($prompt): bool {
        return $prompt->contains('Welcome')
            && $prompt->contains('A long body that should be translated.')
            && !$prompt->contains('Titolo esistente');
    });
});
