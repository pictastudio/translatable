<?php

namespace Tests\Eloquent;

use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Vegetable extends Eloquent implements TranslatableContract
{
    use Translatable;

    public $translatedAttributes = ['name'];

    public $localeKey;

    protected $primaryKey = 'identity';

    protected $fillable = ['quantity'];
}
