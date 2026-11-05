<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot;

use Illuminate\Contracts\Container\Container;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\GrokBot\Commands\PingCommand;

class GrokBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/grok-bot.php', 'grok-bot');
    }

    public function boot(): void
    {
        Notification::resolved(function (ChannelManager $channels): void {
            $channels->extend(GrokBotChannel::NAME, fn (Container $app): GrokBotChannel => $app->make(GrokBotChannel::class));
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/grok-bot.php' => config_path('grok-bot.php'),
            ], 'grok-bot-config');

            $this->commands([PingCommand::class]);
        }
    }
}
