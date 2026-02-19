<?php

namespace PictaStudio\Translatable\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @see PictaStudio\Translatable\Translatable
 */
class Translatable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'translatable';
    }
}
