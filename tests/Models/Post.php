<?php

namespace PictaStudio\Translatable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;

class Post extends Model implements TranslatableContract
{
    use Translatable;

    public array $translatedAttributes = ['title', 'summary'];

    protected $fillable = [
        'slug',
        'title',
        'summary',
    ];
}
