<?php

namespace Tests\Eloquent;

use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Person extends Eloquent implements TranslatableContract
{
    use Translatable;

    /**
     * Array with the fields translated in the Translation table.
     *
     * @var array
     */
    public $translatedAttributes = ['name'];

    /**
     * The database field being used to define the locale parameter in the translation model.
     * Defaults to 'locale'.
     *
     * @var string
     */
    public $localeKey;

    protected $table = 'people';

    /**
     * Mutate name attribute into upper-case.
     *
     * @return string
     */
    public function getNameAttribute($value)
    {
        return ucwords($value);
    }
}
