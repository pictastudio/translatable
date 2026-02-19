<?php

namespace PictaStudio\Translatable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Translatable\Translatable;

class Post extends Model
{
    use Translatable;

    public array $translatedAttributes = ['title', 'summary'];

    protected $fillable = [
        'slug',
        'title',
        'summary',
    ];
}
