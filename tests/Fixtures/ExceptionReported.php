<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\GrokBot\GrokBotMessage;
use Throwable;

class ExceptionReported extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $class,
        public string $message,
        public string $location,
        public string $trace,
        public ?string $url,
    ) {}

    public static function from(Throwable $e): self
    {
        return new self(
            $e::class,
            $e->getMessage(),
            $e->getFile().':'.$e->getLine(),
            mb_substr($e->getTraceAsString(), 0, 4000),
            app()->runningInConsole() ? null : request()->fullUrl(),
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['grok-bot'];
    }

    public function toGrokBot(object $notifiable): GrokBotMessage
    {
        return GrokBotMessage::create("{$this->class}: {$this->message}")
            ->data([
                'event' => 'exception.reported',
                'repository' => 'acme/shop',
                'commit' => config('app.commit'),
                'location' => $this->location,
                'url' => $this->url,
                'trace' => $this->trace,
            ]);
    }
}
