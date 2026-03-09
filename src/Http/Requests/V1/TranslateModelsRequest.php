<?php

namespace PictaStudio\Translatable\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class TranslateModelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => ['required', 'string'],
            'id' => ['nullable'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['nullable'],
            'source_locale' => ['nullable', 'string'],
            'target_locales' => ['nullable', 'array'],
            'target_locales.*' => ['required', 'string'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['required', 'string'],
            'force' => ['nullable', 'boolean'],
            'provider' => ['nullable', 'string'],
            'model_name' => ['nullable', 'string'],
        ];
    }
}
