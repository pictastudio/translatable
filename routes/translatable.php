<?php

use Illuminate\Support\Facades\Route;
use PictaStudio\Translatable\Http\Controllers\TranslateModelsController;

Route::post('/translate', TranslateModelsController::class)
    ->name('translate');
