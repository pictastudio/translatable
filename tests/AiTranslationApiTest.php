<?php

use PictaStudio\Translatable\Ai\Agents\TranslateModelAgent;
use PictaStudio\Translatable\Tests\Models\Post;

it('translates selected models through the api endpoint', function (): void {
    $post = Post::query()->create([
        'slug' => 'about',
        'title:en' => 'About us',
        'summary:en' => 'We build multilingual websites.',
    ]);

    TranslateModelAgent::fake([
        [
            'translations' => [
                ['locale' => 'fr', 'attribute' => 'title', 'value' => 'A propos de nous'],
                ['locale' => 'fr', 'attribute' => 'summary', 'value' => 'Nous construisons des sites web multilingues.'],
            ],
        ],
    ])->preventStrayPrompts();

    $this->postJson('/translatable/ai/translate', [
        'model' => Post::class,
        'id' => $post->getKey(),
        'source_locale' => 'en',
        'target_locales' => ['fr'],
    ])
        ->assertOk()
        ->assertJsonPath('data.0.model_type', Post::class)
        ->assertJsonPath('data.0.translated.fr.title', 'A propos de nous')
        ->assertJsonPath('meta.translated_pairs', 2);

    $post->refresh();

    expect($post->{'title:fr'})->toBe('A propos de nous');
    expect($post->{'summary:fr'})->toBe('Nous construisons des sites web multilingues.');
});
