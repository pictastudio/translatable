<?php

namespace PictaStudio\Translatable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;

class Product extends Model implements TranslatableContract
{
    use Translatable;

    public array $translatedAttributes = ['name'];

    protected $fillable = [
        'stock',
        'name',
    ];
}
