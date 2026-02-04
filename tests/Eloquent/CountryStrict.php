<?php

namespace Tests\Eloquent;

use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class CountryStrict extends Eloquent implements TranslatableContract
{
    use Translatable;

    /**
     * Array with the fields translated in the Translation table.
     *
     * @var array
     */
    public $translatedAttributes = ['name'];

    /**
     * Column containing the locale in the translation table.
     * Defaults to 'locale'.
     *
     * @var string
     */
    public $localeKey;

    public $table = 'countries';

    /**
     * Add your translated attributes here if you want
     * fill them with mass assignment.
     *
     * @var array
     */
    public $fillable = ['code'];

    protected $softDelete = true;
}
