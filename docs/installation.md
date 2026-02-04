# Installation

## Install package

Add the package in your `composer.json` by executing the command.

```bash
composer require pictastudio/laravel-translatable
```

## Configuration

We copy the configuration file to our project.

```bash
php artisan vendor:publish --tag=translatable
```

After this you will have to configure the `locales` your app should use.

```php
'locales' => [
    'en',
    'fr',
    'es' => [
        'MX', // mexican spanish
        'CO', // colombian spanish
    ],
],
```

{% hint style="info" %}
There isn't any restriction for the format of the locales. Feel free to use whatever suits you better, like "eng" instead of "en", or "el" instead of "gr". The important is to define your locales and stick to them.
{% endhint %}

That's the only configuration key you **have** to adjust. All the others have a working default value and are described in the configuration file itself.

## Migrations

This package uses a **single polymorphic table** for all translations.

{% code title="create\_posts\_table.php" %}

```php
Schema::create('posts', function(Blueprint $table) {
    $table->increments('id');
    $table->string('author');
    $table->timestamps();
});
```

{% endcode %}

{% code title="create\_translations\_table.php" %}

```php
Schema::create('translations', function(Blueprint $table) {
    $table->increments('id');
    $table->morphs('translatable');
    $table->string('locale')->index();
    $table->string('attribute');
    $table->text('value')->nullable();

    $table->unique(['translatable_type', 'translatable_id', 'locale', 'attribute'], 'translations_unique');
});
```

{% endcode %}

## Models

The translatable model `Post` should [use the trait](http://www.sitepoint.com/using-traits-in-php-5-4/) `PictaStudio\Translatable\Translatable`. The array `$translatedAttributes` contains the names of the fields being translated.

{% code title="Post.php" %}

```php
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;

class Post extends Model implements TranslatableContract
{
    use Translatable;

    public $translatedAttributes = ['title', 'content'];
    protected $fillable = ['author'];
}
```

{% endcode %}
