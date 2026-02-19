<?php

namespace PictaStudio\Translatable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \PictaStudio\Translatable\Locales
 */
class Translatable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'translatable.locales';
    }
}
