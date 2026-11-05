<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot;

use NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification;

/**
 * A Grok Bot routine with a webhook trigger: where to POST and the key that
 * authorizes it.
 */
final readonly class GrokBotRoutine
{
    public string $authorization;

    public function __construct(
        public string $url,
        string $key,
        public ?string $name = null,
    ) {
        $key = trim($key);

        $this->authorization = str_starts_with(strtolower($key), 'bearer ') ? 'Bearer '.trim(substr($key, 7)) : 'Bearer '.$key;
    }

    public static function make(string $url, string $key): self
    {
        return new self($url, $key);
    }

    /**
     * The routine configured under `grok-bot.routines.{name}`.
     *
     * @throws CouldNotSendNotification
     */
    public static function fromConfig(string $name): self
    {
        $routine = config("grok-bot.routines.{$name}");

        if (! is_array($routine)) {
            throw CouldNotSendNotification::routineNotConfigured($name);
        }

        $url = is_string($routine['url'] ?? null) ? trim($routine['url']) : '';
        $key = is_string($routine['key'] ?? null) ? trim($routine['key']) : '';

        if ($url === '' || $key === '') {
            throw CouldNotSendNotification::routineMissingCredentials($name, $url === '' ? 'url' : 'key');
        }

        return new self($url, $key, $name);
    }

    /**
     * A name for errors and logs that never reveals the key.
     */
    public function label(): string
    {
        return $this->name ?? (string) parse_url($this->url, PHP_URL_PATH);
    }
}
