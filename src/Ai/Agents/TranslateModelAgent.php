<?php

namespace PictaStudio\Translatable\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\{Agent, HasStructuredOutput};
use Laravel\Ai\Promptable;

class TranslateModelAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<int, string>  $targetLocales
     * @param  array<int, string>  $attributes
     */
    public function __construct(
        protected string $sourceLocale,
        protected array $targetLocales,
        protected array $attributes,
        protected int $translationCount,
    ) {}

    public function instructions(): string
    {
        return "You translate Laravel model content from {$this->sourceLocale} into the requested locales. " .
            'Preserve meaning, formatting, placeholders, HTML, Markdown, URLs, email addresses, and line breaks. ' .
            'Return one translated value for each requested locale and attribute pair.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'translations' => $schema->array()
                ->required()
                ->min($this->translationCount)
                ->max($this->translationCount)
                ->items(
                    $schema->object([
                        'locale' => $schema->string()->required()->enum($this->targetLocales),
                        'attribute' => $schema->string()->required()->enum($this->attributes),
                        'value' => $schema->string()->required()->min(1),
                    ])->withoutAdditionalProperties()
                ),
        ];
    }
}
