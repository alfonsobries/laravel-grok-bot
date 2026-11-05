<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot\Tests\Fixtures;

use Illuminate\Notifications\Notifiable;

class Team
{
    use Notifiable;

    public function __construct(private readonly mixed $route = null) {}

    public function routeNotificationForGrokBot(): mixed
    {
        return $this->route;
    }
}
