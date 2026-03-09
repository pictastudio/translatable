<?php

namespace PictaStudio\Translatable\Notifications;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Notifications\Dispatcher;
use InvalidArgumentException;
use PictaStudio\Translatable\Contracts\TranslationRequestNotifier;

class LaravelTranslationRequestNotifier implements TranslationRequestNotifier
{
    public function __construct(
        protected Dispatcher $notifications,
        protected Container $container,
    ) {}

    public function notify(object $notifiable, array $summary): void
    {
        $channels = array_values(array_filter(
            (array) config('translatable.ai.notifications.channels', ['mail', 'database']),
            static fn (mixed $channel): bool => is_string($channel) && $channel !== ''
        ));

        if ($channels === []) {
            return;
        }

        $this->notifications->send($notifiable, $this->makeNotification($summary, $channels));
    }

    protected function makeNotification(array $summary, array $channels): TranslationCompletedNotification
    {
        $notification = $this->container->make(TranslationCompletedNotification::class, [
            'summary' => $summary,
            'channels' => $channels,
        ]);

        if (!$notification instanceof TranslationCompletedNotification) {
            throw new InvalidArgumentException(sprintf(
                'The configured translation notification must be an instance of [%s].',
                TranslationCompletedNotification::class
            ));
        }

        return $notification;
    }
}
