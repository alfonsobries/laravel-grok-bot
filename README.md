# Grok Bot notification channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alfonsobries/laravel-grok-bot.svg?style=flat-square)](https://packagist.org/packages/alfonsobries/laravel-grok-bot)
[![Tests](https://img.shields.io/github/actions/workflow/status/alfonsobries/laravel-grok-bot/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/alfonsobries/laravel-grok-bot/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/alfonsobries/laravel-grok-bot.svg?style=flat-square)](LICENSE.md)

**[laravel-grok-bot.vercel.app](https://laravel-grok-bot.vercel.app)**

Send Laravel notifications to a [Grok Bot](https://cursor.com/help/grok-bot/routines) routine. Each notification starts a run of the routine with your JSON as its input, and the Bot does what the routine's instruction says: tell you, investigate, open an issue, ask before acting.

```php
public function toGrokBot(object $notifiable): GrokBotMessage
{
    return GrokBotMessage::create('Scraper marcate failed after 3 attempts')
        ->data(['source' => 'marcate', 'error' => 'HTTP 403']);
}
```

## Contents

- [Installation](#installation)
- [Setting up a routine](#setting-up-a-routine)
- [Usage](#usage)
- [Routing](#routing)
- [Message methods](#message-methods)
- [Errors](#errors)
- [Webhook](#webhook)
- [AI agents](#ai-agents)
- [Testing](#testing)

## Installation

```bash
composer require alfonsobries/laravel-grok-bot
```

Requires PHP 8.2+ and Laravel 12 or 13. The service provider registers itself.

## Setting up a routine

1. In Grok Bot, ask the Bot to add a webhook trigger to a routine. Write the routine's instruction: what the JSON it receives means and what to do with it.
2. Make sure the routine is **Active**.
3. Open the routine on desktop and copy the **POST to** URL and the **key** from its **Webhook** section.
4. Add them to `.env`:

```dotenv
GROK_BOT_WEBHOOK_URL=https://api2.cursor.sh/automations/webhook/<id>
GROK_BOT_WEBHOOK_KEY=crsr_...
```

5. Check the connection:

```bash
php artisan grok-bot:ping
```

The ping sends `{"type": "ping", "message": "..."}`. Tell the routine to ignore pings in its instruction so a check never bothers anyone.

### More than one routine

Publish the config and add a routine per purpose:

```bash
php artisan vendor:publish --tag=grok-bot-config
```

```php
// config/grok-bot.php
'routines' => [
    'default' => [
        'url' => env('GROK_BOT_WEBHOOK_URL'),
        'key' => env('GROK_BOT_WEBHOOK_KEY'),
    ],
    'fraud' => [
        'url' => env('GROK_BOT_FRAUD_WEBHOOK_URL'),
        'key' => env('GROK_BOT_FRAUD_WEBHOOK_KEY'),
    ],
],
```

The key works with or without its `Bearer ` prefix.

| Option | Env | Default | |
| --- | --- | --- | --- |
| `default` | `GROK_BOT_ROUTINE` | `default` | Routine used when nothing else names one |
| `timeout` | `GROK_BOT_TIMEOUT` | `10` | Seconds to wait for Grok Bot |
| `retries` | `GROK_BOT_RETRIES` | `2` | Extra attempts after a connection error or a 5xx |
| `retry_delay_ms` | `GROK_BOT_RETRY_DELAY_MS` | `500` | Wait between attempts |

## Usage

Return `grok-bot` from `via()` and build the message in `toGrokBot()`:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\GrokBot\GrokBotMessage;

class ScraperFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $source,
        public int $attempts,
        public string $error,
    ) {}

    public function via(object $notifiable): array
    {
        return ['grok-bot'];
    }

    public function toGrokBot(object $notifiable): GrokBotMessage
    {
        return GrokBotMessage::create("Scraper {$this->source} failed after {$this->attempts} attempts")
            ->data([
                'event' => 'scraper.failed',
                'source' => $this->source,
                'attempts' => $this->attempts,
                'error' => $this->error,
            ]);
    }
}
```

The routine receives:

```json
{
    "message": "Scraper marcate failed after 3 attempts",
    "event": "scraper.failed",
    "source": "marcate",
    "attempts": 3,
    "error": "HTTP 403"
}
```

`toGrokBot()` may also return a string (sent as `{"message": "..."}`), an array (sent as the whole body), or `null` to skip the notification.

Queue the notification: it is an HTTP call, and a queued job retries and shows up when it fails.

## Routing

The routine is chosen in this order:

1. The message: `GrokBotMessage::create('...')->routine('fraud')`.
2. The notifiable: a `routeNotificationForGrokBot()` method.
3. The `default` routine.

Each of these accepts a routine name from the config, a `GrokBotRoutine`, or an array with `url` and `key`.

```php
// On demand, without a notifiable
Notification::route('grok-bot', 'fraud')->notify(new SuspiciousOrder($order));

// A model that knows its routine
public function routeNotificationForGrokBot(Notification $notification): string
{
    return 'fraud';
}

// A routine built at runtime, for example one stored per team
use NotificationChannels\GrokBot\GrokBotRoutine;

public function routeNotificationForGrokBot(Notification $notification): GrokBotRoutine
{
    return GrokBotRoutine::make($this->grok_bot_url, $this->grok_bot_key);
}
```

## Message methods

| Method | |
| --- | --- |
| `GrokBotMessage::create(?string $message)` | New message; the text goes in `message` |
| `message(string $message)` | Set the text |
| `data(array $data)` | Merge fields into the body, next to `message` |
| `with(string $key, mixed $value)` | Set one field |
| `routine(GrokBotRoutine\|string $routine)` | Send to this routine |

Keep `message` to one readable line and put the facts in flat fields with stable names. Send what the Bot needs to act (ids, links, the command to retry) and nothing it should not see.

## Errors

When the routine does not start a run, the channel fires `Illuminate\Notifications\Events\NotificationFailed` (with `routine`, `status` and `exception` in its data) and throws `NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification`. Its message names the cause:

| Status | Cause |
| --- | --- |
| 401 | The key is invalid or was regenerated |
| 400 | The routine is not active, or hit its run rate limit |
| 404 | The webhook URL no longer exists |
| 5xx, connection error | Retried `retries` times, then thrown |

The key never appears in an exception, an event or the command output.

## Webhook

What the channel relies on:

- `POST <url>` with `Authorization: Bearer <key>` and a JSON body of any shape.
- `200 {"success": true, "runUuid": "..."}` means a run started, not that the Bot finished. The result shows in the Bot's chat.
- Grok Bot does not retry a refused call, and a routine runs at most 200 times an hour (300 across all routines). Debounce events that come in bursts.

## AI agents

The package ships a [Laravel Boost](https://github.com/laravel/boost) guideline and a `grok-bot-notifications` skill. Run `php artisan boost:install` after requiring the package, and your agent knows how to wire a new notification to a routine end to end.

## Testing

```php
Http::fake(['api2.cursor.sh/*' => Http::response(['success' => true, 'runUuid' => 'run-1'])]);

Notification::route('grok-bot', 'default')->notify(new ScraperFailed('marcate', 3, 'HTTP 403'));

Http::assertSent(fn ($request) => $request['source'] === 'marcate');
```

Run the package's own suite with `composer test`.

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Security

Report vulnerabilities to alfonso@vexilo.com instead of opening an issue.

## Credits

- [Alfonso Bribiesca](https://github.com/alfonsobries)
- [All contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE](LICENSE.md).
