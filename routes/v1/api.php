<?php

use Illuminate\Support\Facades\Route;
use PictaStudio\Translatable\Http\Controllers\Api\V1\{LocaleController, MissingTranslationsController, TranslatableModelsController, TranslateController};

Route::get('locales', [LocaleController::class, 'index'])->name('locales.index');
Route::get('models', [TranslatableModelsController::class, 'index'])->name('models.index');
Route::get('missing-translations', [MissingTranslationsController::class, 'index'])->name('missing-translations.index');
Route::post('translate', [TranslateController::class, 'store'])->name('translate');
