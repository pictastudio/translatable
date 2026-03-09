<?php

namespace PictaStudio\Translatable\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListMissingTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => ['nullable', 'string'],
            'source_locale' => ['nullable', 'string'],
            'target_locales' => ['nullable', 'array'],
            'target_locales.*' => ['required', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
