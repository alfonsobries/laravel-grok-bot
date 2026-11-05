<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The JSON body a Grok Bot routine receives with its instruction. The text
 * goes in `message`; anything added with data() or with() sits next to it.
 *
 * @implements Arrayable<string, mixed>
 */
final class GrokBotMessage implements Arrayable, JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data = [];

    private GrokBotRoutine|string|null $routine = null;

    public function __construct(private ?string $message = null) {}

    public static function create(?string $message = null): self
    {
        return new self($message);
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Merge fields into the body.
     *
     * @param  array<string, mixed>  $data
     */
    public function data(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function with(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Send to this routine instead of the notifiable's: a name from
     * `grok-bot.routines`, or a routine built at runtime.
     */
    public function routine(GrokBotRoutine|string $routine): self
    {
        $this->routine = $routine;

        return $this;
    }

    public function getRoutine(): GrokBotRoutine|string|null
    {
        return $this->routine;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->message === null ? $this->data : ['message' => $this->message, ...$this->data];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
