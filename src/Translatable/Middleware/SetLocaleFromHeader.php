<?php

namespace PictaStudio\Translatable\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('translatable.use_locale_header')) {
            return $next($request);
        }

        $locale = $request->header('Locale');

        if (!in_array($locale, $this->getAvailableLocales())) {
            return $next($request);
        }

        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function getAvailableLocales(): array
    {
        $locales = [];

        foreach (config('translatable.locales') as $key => $value) {
            if (is_array($value)) {
                $locales[] = $key;

                foreach ($value as $region) {
                    $locales[] = $key . config('translatable.locale_separator') . $region;
                }
            } else {
                $locales[] = $value;
            }
        }

        return $locales;
    }
}
