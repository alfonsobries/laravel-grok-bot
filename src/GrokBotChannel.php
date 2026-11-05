<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification;

class GrokBotChannel
{
    public const NAME = 'grok-bot';

    public function __construct(
        private readonly GrokBot $grokBot,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return array{success: true, runUuid?: string}|null
     *
     * @throws CouldNotSendNotification
     */
    public function send(object $notifiable, Notification $notification): ?array
    {
        if (! method_exists($notification, 'toGrokBot')) {
            return null;
        }

        $message = $this->toMessage($notification->toGrokBot($notifiable));

        if ($message === null) {
            return null;
        }

        try {
            $routine = $this->routine($message, $notifiable, $notification);

            return $this->grokBot->send($routine, $message->toArray());
        } catch (CouldNotSendNotification $exception) {
            $this->events->dispatch(new NotificationFailed($notifiable, $notification, self::NAME, [
                'routine' => isset($routine) ? $routine->label() : null,
                'status' => $exception->status,
                'exception' => $exception,
            ]));

            throw $exception;
        }
    }

    /**
     * @throws CouldNotSendNotification
     */
    private function toMessage(mixed $message): ?GrokBotMessage
    {
        return match (true) {
            $message === null => null,
            $message instanceof GrokBotMessage => $message,
            is_string($message) => GrokBotMessage::create($message),
            is_array($message) => GrokBotMessage::create()->data(array_combine(array_map(strval(...), array_keys($message)), $message)),
            default => throw CouldNotSendNotification::invalidMessage(get_debug_type($message)),
        };
    }

    /**
     * The message's routine, else the notifiable's route, else the default.
     *
     * @throws CouldNotSendNotification
     */
    private function routine(GrokBotMessage $message, object $notifiable, Notification $notification): GrokBotRoutine
    {
        $route = $message->getRoutine();

        if ($route === null && method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor(self::NAME, $notification);
        }

        return match (true) {
            $route instanceof GrokBotRoutine => $route,
            is_array($route) => GrokBotRoutine::make(self::string($route['url'] ?? null), self::string($route['key'] ?? null)),
            is_string($route) && $route !== '' => GrokBotRoutine::fromConfig($route),
            default => GrokBotRoutine::fromConfig(config()->string('grok-bot.default', 'default')),
        };
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
