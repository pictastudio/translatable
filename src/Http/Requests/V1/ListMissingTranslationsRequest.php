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
            'accepted' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('accepted')) {
            return;
        }

        $accepted = filter_var($this->input('accepted'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (is_bool($accepted)) {
            $this->merge([
                'accepted' => $accepted,
            ]);
        }
    }
}
