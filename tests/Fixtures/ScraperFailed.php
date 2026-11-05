<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot\Tests\Fixtures;

use Closure;
use Illuminate\Notifications\Notification;

class ScraperFailed extends Notification
{
    public function __construct(private readonly Closure $build) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['grok-bot'];
    }

    public function toGrokBot(object $notifiable): mixed
    {
        return ($this->build)($notifiable);
    }
}
