<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot\Tests;

use NotificationChannels\GrokBot\GrokBotServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GrokBotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('grok-bot.routines', [
            'default' => ['url' => 'https://api2.cursor.sh/automations/webhook/default-routine', 'key' => 'crsr_default'],
            'alerts' => ['url' => 'https://api2.cursor.sh/automations/webhook/alerts-routine', 'key' => 'Bearer crsr_alerts'],
        ]);
        $app['config']->set('grok-bot.retry_delay_ms', 0);
    }
}
