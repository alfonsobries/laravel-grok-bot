<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Throwable;

final class CouldNotSendNotification extends Exception
{
    public ?int $status = null;

    public static function routineNotConfigured(string $name): self
    {
        return new self("Grok Bot routine `{$name}` is not configured. Add it under `grok-bot.routines`.");
    }

    public static function routineMissingCredentials(string $name, string $field): self
    {
        return new self("Grok Bot routine `{$name}` has no {$field}. Copy it from the routine's Webhook section.");
    }

    public static function invalidMessage(string $type): self
    {
        return new self("toGrokBot() must return a GrokBotMessage, an array or a string, {$type} given.");
    }

    public static function couldNotConnect(string $routine, Throwable $previous): self
    {
        return new self("Could not reach Grok Bot routine `{$routine}`: {$previous->getMessage()}", 0, $previous);
    }

    public static function rejected(string $routine, Response $response): self
    {
        $status = $response->status();
        $detail = self::detail($response);

        $reason = match (true) {
            $status === 401 => 'the key is invalid or was regenerated',
            $status === 400 && str_contains(strtolower($detail), 'disabled') => 'the routine is not active',
            $status === 404 => 'the webhook URL no longer exists',
            $status === 429 || str_contains(strtolower($detail), 'rate limit') => 'the routine hit its run rate limit',
            $status >= 500 => 'Grok Bot had an error',
            default => 'the request was refused',
        };

        $exception = new self("Grok Bot routine `{$routine}` did not start a run ({$status}): {$reason}. {$detail}");
        $exception->status = $status;

        return $exception;
    }

    private static function detail(Response $response): string
    {
        $json = $response->json();
        $detail = is_array($json) ? ($json['error'] ?? $json['message'] ?? null) : null;

        return Str::limit(is_string($detail) ? $detail : trim($response->body()), 300);
    }
}
