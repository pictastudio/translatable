<?php

namespace PictaStudio\Translatable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Translatable\Translatable;

class Product extends Model
{
    use Translatable;

    public array $translatedAttributes = ['name'];

    protected $fillable = [
        'stock',
        'name',
    ];
}
