<?php

namespace PictaStudio\Translatable\Contracts;

interface TranslationRequestNotifier
{
    /**
     * @param  array{
     *     model: string,
     *     model_class: class-string<\Illuminate\Database\Eloquent\Model>,
     *     requested_ids: array<int, int|string>,
     *     matched_models: int,
     *     translated_pairs: int,
     *     source_locale: string|null,
     *     target_locales: array<int, string>,
     *     attributes: array<int, string>,
     *     force: bool,
     *     results: array<int, array{
     *         model_type: string,
     *         model_class: class-string<\Illuminate\Database\Eloquent\Model>,
     *         model_id: mixed,
     *         source_locale: string,
     *         target_locales: array<int, string>,
     *         translated_attributes: array<int, string>,
     *         requested_count: int,
     *         translated_count: int,
     *         translated: array<string, array<string, string>>,
     *         skipped: array<int, array{locale: string, attribute: string, reason: string}>
     *     }>
     * }  $summary
     */
    public function notify(object $notifiable, array $summary): void;
}
