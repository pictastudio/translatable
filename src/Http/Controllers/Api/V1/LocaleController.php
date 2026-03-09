<?php

namespace PictaStudio\Translatable\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use PictaStudio\Translatable\Http\Controllers\Api\Controller;
use PictaStudio\Translatable\Locales;

class LocaleController extends Controller
{
    public function index(Locales $locales): JsonResponse
    {
        $defaultLocale = $locales->current();
        $data = array_map(
            static fn (string $locale): array => [
                'code' => $locale,
                'is_default' => $locale === $defaultLocale,
            ],
            $locales->all()
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'default_locale' => $defaultLocale,
            ],
        ]);
    }
}
