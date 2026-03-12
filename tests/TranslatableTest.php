<?php

use Illuminate\Support\Facades\{DB, Date};
use PictaStudio\Translatable\Tests\Models\{Post, Product};

use function Pest\Laravel\assertDatabaseHas;

it('stores translations for different models in the same polymorphic table', function (): void {
    $post = Post::query()->create([
        'slug' => 'welcome',
        'title:en' => 'Welcome',
        'title:it' => 'Benvenuto',
    ]);

    $product = Product::query()->create([
        'stock' => 20,
        'name:en' => 'Shoes',
        'name:it' => 'Scarpe',
    ]);

    assertDatabaseHas('translations', [
        'translatable_type' => Post::class,
        'translatable_id' => $post->id,
        'locale' => 'en',
        'attribute' => 'title',
        'value' => 'Welcome',
    ]);

    assertDatabaseHas('translations', [
        'translatable_type' => Product::class,
        'translatable_id' => $product->id,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Scarpe',
    ]);

    assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Shoes',
    ]);
});

it('reads and writes translated attributes using locale aware accessors', function (): void {
    $post = Post::query()->create([
        'slug' => 'hello-world',
        'title:en' => 'Hello',
        'title:it' => 'Ciao',
    ]);

    app()->setLocale('it');
    expect($post->title)->toBe('Ciao');
    expect($post->{'title:en'})->toBe('Hello');

    $post->{'title:it'} = 'Salve';
    $post->save();
    $post->refresh();

    expect($post->{'title:it'})->toBe('Salve');
});

it('falls back to base table values when translations are missing', function (): void {
    $now = now();

    DB::table('products')->insert([
        'name' => 'Legacy name',
        'stock' => 20,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    app()->setLocale('fr');

    $product = Product::query()->firstOrFail();

    expect($product->name)->toBe('Legacy name');
});

it('supports locale keyed mass assignment and fallback', function (): void {
    $post = Post::query()->create([
        'slug' => 'roadmap',
        'en' => ['title' => 'Roadmap'],
        'it' => ['title' => 'Tabella di marcia'],
    ]);

    app()->setLocale('fr');
    expect($post->title)->toBe('Roadmap');
});

it('updates only the translation record for localized updates on existing models', function (): void {
    $product = Product::query()->create([
        'stock' => 20,
        'name:en' => 'Shoes',
        'name:it' => 'Scarpe',
    ]);

    app()->setLocale('it');

    $product->update([
        'name' => 'Stivali',
    ]);
    $product->refresh();

    expect($product->{'name:it'})->toBe('Stivali')
        ->and($product->getRawOriginal('name'))->toBe('Shoes');

    assertDatabaseHas('translations', [
        'translatable_type' => Product::class,
        'translatable_id' => $product->id,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Stivali',
    ]);

    assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Shoes',
    ]);
});

it('provides translate or new helpers', function (): void {
    $post = Post::query()->create([
        'slug' => 'faq',
        'title:en' => 'FAQ',
    ]);

    $post->translateOrNew('it')->title = 'Domande frequenti';
    $post->save();
    $post->refresh();

    expect($post->translate('it')?->title)->toBe('Domande frequenti');
});

it('fills translation timestamps on create', function (): void {
    $post = Post::query()->create([
        'slug' => 'timed',
        'title:en' => 'Timestamped',
    ]);

    $translation = DB::table('translations')
        ->where('translatable_type', Post::class)
        ->where('translatable_id', $post->id)
        ->where('locale', 'en')
        ->where('attribute', 'title')
        ->first();

    expect($translation)->not->toBeNull();
    expect($translation?->created_at)->not->toBeNull();
    expect($translation?->updated_at)->not->toBeNull();
    expect($translation?->generated_by)->toBe('user');
    expect($translation?->accepted_at)->not->toBeNull();
});

it('fills translation timestamps on update', function (): void {
    $createdAt = Date::parse('2026-02-20 10:00:00');
    $updatedAt = Date::parse('2026-02-20 11:00:00');

    try {
        Date::setTestNow($createdAt);

        $post = Post::query()->create([
            'slug' => 'timed-update',
            'title:en' => 'Timestamped',
        ]);

        Date::setTestNow($updatedAt);

        $post->update([
            'title:en' => 'Updated',
        ]);
        $post->refresh();
    } finally {
        Date::setTestNow();
    }

    $translation = DB::table('translations')
        ->where('translatable_type', Post::class)
        ->where('translatable_id', $post->getKey())
        ->where('locale', 'en')
        ->where('attribute', 'title')
        ->first();

    expect($translation)->not->toBeNull();
    expect(Date::parse($translation?->created_at)->format('Y-m-d H:i:s'))->toBe('2026-02-20 10:00:00');
    expect(Date::parse($translation?->updated_at)->format('Y-m-d H:i:s'))->toBe('2026-02-20 11:00:00');
    expect($translation?->generated_by)->toBe('user');
    expect(Date::parse($translation?->accepted_at)->format('Y-m-d H:i:s'))->toBe('2026-02-20 11:00:00');
});
