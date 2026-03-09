<?php

namespace PictaStudio\Translatable\Http\Requests\V1;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\{Rule, Validator};
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Locales;
use PictaStudio\Translatable\Support\TranslatableModelRegistry;

class TranslateModelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => ['nullable', 'string'],
            'id' => ['nullable', $this->scalarRule()],
            'ids' => ['nullable', 'array', 'min:1'],
            'ids.*' => ['bail', 'required', 'distinct', $this->scalarRule()],
            'source_locale' => ['required', 'string', Rule::in($this->availableLocales())],
            'target_locales' => ['required', 'array', 'min:1'],
            'target_locales.*' => ['bail', 'required', 'string', 'distinct', Rule::in($this->availableLocales())],
            'attributes' => ['nullable', 'array', 'min:1'],
            'attributes.*' => ['bail', 'required', 'string', 'distinct'],
            'force' => ['sometimes', 'boolean'],
            'provider' => ['nullable', 'string', Rule::in($this->availableProviders())],
            'model_name' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $model = $this->input('model');
            $hasModel = is_string($model) && $model !== '';
            $hasIds = $this->resolvedIds() !== [];

            if (!$hasModel && $hasIds) {
                $validator->errors()->add('model', 'The model field is required when model ids are provided.');
            }

            if ($hasModel && !$hasIds) {
                $validator->errors()->add('ids', 'At least one model id must be provided.');
            }

            $modelClass = $hasModel ? $this->resolveTranslatableModelClass($model) : null;

            if ($hasModel && $modelClass === null) {
                $validator->errors()->add(
                    'model',
                    "The model [{$model}] must extend Eloquent and implement the translatable contract."
                );
            }

            $attributes = $this->input('attributes');

            if (!is_array($attributes) || $attributes === []) {
                return;
            }

            $allowedAttributes = $modelClass !== null
                ? $this->translatedAttributesForModel($modelClass)
                : $this->translatedAttributesAcrossModels();

            foreach (array_values($attributes) as $index => $attribute) {
                if (!is_string($attribute) || $attribute === '') {
                    continue;
                }

                if (!in_array($attribute, $allowedAttributes, true)) {
                    $validator->errors()->add(
                        "attributes.{$index}",
                        "The selected attributes.{$index} is invalid."
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if (!$this->filled('source_locale')) {
            $payload['source_locale'] = $this->defaultSourceLocale();
        }

        $targetLocales = $this->input('target_locales');

        if ($targetLocales === null || $targetLocales === []) {
            $payload['target_locales'] = $this->availableLocales();
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    protected function defaultSourceLocale(): string
    {
        $configuredLocale = config('translatable.locale');

        if (is_string($configuredLocale) && $configuredLocale !== '') {
            return $configuredLocale;
        }

        $appLocale = config('app.locale');

        if (is_string($appLocale) && $appLocale !== '') {
            return $appLocale;
        }

        return app(Locales::class)->current();
    }

    /**
     * @return array<int, int|string>
     */
    protected function resolvedIds(): array
    {
        $ids = [];
        $id = $this->input('id');

        if ($id !== null && $id !== '') {
            $ids[] = $id;
        }

        foreach ((array) $this->input('ids', []) as $value) {
            if ($value !== null && $value !== '') {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids, SORT_REGULAR));
    }

    /**
     * @return array<int, string>
     */
    protected function availableLocales(): array
    {
        return app(Locales::class)->all();
    }

    /**
     * @return array<int, string>
     */
    protected function availableProviders(): array
    {
        return array_values(array_filter(
            array_keys((array) config('ai.providers', [])),
            static fn (mixed $provider): bool => is_string($provider) && $provider !== ''
        ));
    }

    protected function resolveTranslatableModelClass(string $requestedModel): ?string
    {
        $modelClass = Relation::getMorphedModel($requestedModel);

        if (!is_string($modelClass) || $modelClass === '') {
            $modelClass = $requestedModel;
        }

        if (!is_string($modelClass) || $modelClass === '' || !class_exists($modelClass)) {
            return null;
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        if (!is_subclass_of($modelClass, TranslatableContract::class)) {
            return null;
        }

        return $modelClass;
    }

    /**
     * @param  class-string<Model&TranslatableContract>  $modelClass
     * @return array<int, string>
     */
    protected function translatedAttributesForModel(string $modelClass): array
    {
        /** @var Model&TranslatableContract $model */
        $model = new $modelClass;

        return array_values(array_filter(
            $model->translatedAttributes,
            static fn (mixed $attribute): bool => is_string($attribute) && $attribute !== ''
        ));
    }

    /**
     * @return array<int, string>
     */
    protected function translatedAttributesAcrossModels(): array
    {
        return array_values(array_unique(array_merge(
            [],
            ...array_map(
                fn (string $modelClass): array => $this->translatedAttributesForModel($modelClass),
                app(TranslatableModelRegistry::class)->classes()
            )
        )));
    }

    protected function scalarRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $value !== '') {
                return;
            }

            if (is_int($value) || is_float($value)) {
                return;
            }

            $fail("The {$attribute} field must be a non-empty scalar value.");
        };
    }
}
