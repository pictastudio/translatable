<?php

namespace PictaStudio\Translatable\Middleware;

use Closure;
use Illuminate\Http\Request;
use PictaStudio\Translatable\Locales;

class SetLocaleFromHeader
{
    public function __construct(
        protected Locales $locales,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $headerName = config('translatable.locale_header', 'Locale');

        if (!is_string($headerName) || $headerName === '') {
            return $next($request);
        }

        $locale = $request->headers->get($headerName);
        if (!is_string($locale) || $locale === '') {
            return $next($request);
        }

        $locale = mb_trim(explode(',', $locale, 2)[0]);

        if ($this->locales->has($locale)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
