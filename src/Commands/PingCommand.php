<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot\Commands;

use Illuminate\Console\Command;
use NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification;
use NotificationChannels\GrokBot\GrokBot;
use NotificationChannels\GrokBot\GrokBotRoutine;

class PingCommand extends Command
{
    protected $signature = 'grok-bot:ping
        {routine? : A routine name from config/grok-bot.php (default: the default routine)}
        {--message= : Send this text instead of a ping}';

    protected $description = 'Check that a Grok Bot routine accepts this app\'s webhook calls';

    public function handle(GrokBot $grokBot): int
    {
        $routine = $this->argument('routine');
        $name = is_string($routine) && $routine !== '' ? $routine : config()->string('grok-bot.default', 'default');
        $message = $this->option('message');
        $body = is_string($message) && $message !== ''
            ? ['message' => $message]
            : ['type' => 'ping', 'message' => 'Connectivity test from '.config()->string('app.name', 'Laravel').'. No action needed.'];

        try {
            $response = $grokBot->send(GrokBotRoutine::fromConfig($name), $body);
        } catch (CouldNotSendNotification $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Routine `{$name}` started run ".($response['runUuid'] ?? '(no id)').'. Its result shows in the Bot\'s chat.');

        return self::SUCCESS;
    }
}
