<?php

namespace PictaStudio\Translatable\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TranslationCompletedNotification extends Notification
{
    use Queueable;

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
     * @param  array<int, string>  $channels
     */
    public function __construct(
        protected array $summary,
        protected array $channels,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Translations completed successfully.',
            'model' => $this->summary['model'],
            'model_class' => $this->summary['model_class'],
            'requested_ids' => $this->summary['requested_ids'],
            'matched_models' => $this->summary['matched_models'],
            'translated_pairs' => $this->summary['translated_pairs'],
            'source_locale' => $this->summary['source_locale'],
            'target_locales' => $this->summary['target_locales'],
            'attributes' => $this->summary['attributes'],
            'force' => $this->summary['force'],
            'results' => $this->summary['results'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Translations completed')
            ->line('Your requested translations have finished processing.')
            ->line("Model: {$this->summary['model']}")
            ->line("Matched models: {$this->summary['matched_models']}")
            ->line("Translated pairs: {$this->summary['translated_pairs']}")
            ->line('Target locales: ' . implode(', ', $this->summary['target_locales']));
    }
}
