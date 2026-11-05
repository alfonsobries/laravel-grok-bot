<?php

declare(strict_types=1);

namespace NotificationChannels\GrokBot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification;
use Throwable;

/**
 * Starts a run of a Grok Bot routine by POSTing a JSON body to its webhook.
 * A 200 with `success: true` means the run started, not that it finished.
 */
class GrokBot
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array{success: true, runUuid?: string}
     *
     * @throws CouldNotSendNotification
     */
    public function send(GrokBotRoutine $routine, array $body): array
    {
        try {
            $response = $this->http
                ->withHeaders(['Authorization' => $routine->authorization])
                ->acceptJson()
                ->asJson()
                ->timeout(config()->integer('grok-bot.timeout', 10))
                ->retry(
                    max(1, 1 + config()->integer('grok-bot.retries', 2)),
                    config()->integer('grok-bot.retry_delay_ms', 500),
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    throw: false,
                )
                ->post($routine->url, $body === [] ? (object) [] : $body);
        } catch (ConnectionException $exception) {
            throw CouldNotSendNotification::couldNotConnect($routine->label(), $exception);
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
            throw CouldNotSendNotification::rejected($routine->label(), $response);
        }

        /** @var array{success: true, runUuid?: string} $json */
        return $json;
    }
}
