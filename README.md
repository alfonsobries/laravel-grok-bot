# Grok Bot notification channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alfonsobries/laravel-grok-bot.svg?style=flat-square)](https://packagist.org/packages/alfonsobries/laravel-grok-bot)
[![Tests](https://img.shields.io/github/actions/workflow/status/alfonsobries/laravel-grok-bot/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/alfonsobries/laravel-grok-bot/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/alfonsobries/laravel-grok-bot.svg?style=flat-square)](LICENSE.md)

**[laravel-grok-bot.vercel.app](https://laravel-grok-bot.vercel.app)**

The Grok Bot notification channel for Laravel. Each notification starts a run of a [Grok Bot](https://cursor.com/help/grok-bot/routines) routine with your JSON as its input, and the Bot resolves it the way the routine's instruction says: fixes the bug, answers the ticket, investigates, or asks you first.

```php
public function toGrokBot(object $notifiable): GrokBotMessage
{
    return GrokBotMessage::create("{$this->class}: {$this->message}")
        ->data(['repository' => 'acme/shop', 'location' => $this->location, 'trace' => $this->trace]);
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
    'bugs' => [
        'url' => env('GROK_BOT_BUGS_WEBHOOK_URL'),
        'key' => env('GROK_BOT_BUGS_WEBHOOK_KEY'),
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

Return `grok-bot` from `via()` and build the message in `toGrokBot()`. This one hands every production exception to a Bot that fixes it:

```php
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
```

Report each exception from the handler. Skip the channel's own failures, so a routine that is down does not report itself in a loop:

```php
// bootstrap/app.php
use NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->dontReportDuplicates();

    $exceptions->report(function (Throwable $e): void {
        if (app()->isProduction() && ! $e instanceof CouldNotSendNotification) {
            Notification::route('grok-bot', 'bugs')->notify(ExceptionReported::from($e));
        }
    });
})
```

The routine receives:

```json
{
    "message": "ErrorException: Undefined array key \"currency\"",
    "event": "exception.reported",
    "repository": "acme/shop",
    "commit": "4f5177f",
    "location": "/app/Services/CheckoutService.php:88",
    "url": "https://shop.example.com/checkout",
    "trace": "#0 /app/Http/Controllers/CheckoutController.php(41): ..."
}
```

Its instruction, written in Grok Bot, says how to resolve it:

```text
An exception from production arrives as JSON. Find the cause in the
GitHub repo named in "repository" at "commit". If it is a bug in our code,
write a failing test, fix it, and open a draft pull request; send me the
link here. If it is not (a third-party outage, a bad deploy, a bot hitting
a bad URL), tell me in one line and do nothing else.
Ignore bodies with "type": "ping".
```

More things a Bot can resolve the same way:

- **A production exception.** Opens a draft pull request with a test and the fix.
- **A support ticket.** Looks the customer up, answers from your help center, asks before refunds.
- **A failed payment.** Checks the processor, retries or emails the customer with the right link.
- **A queue job that keeps failing.** Reads the payload and the error, and tells you what to change.

`toGrokBot()` may also return a string (sent as `{"message": "..."}`), an array (sent as the whole body), or `null` to skip the notification.

Queue the notification: it is an HTTP call, and a queued job retries and shows up when it fails.

## Routing

The routine is chosen in this order:

1. The message: `GrokBotMessage::create('...')->routine('bugs')`.
2. The notifiable: a `routeNotificationForGrokBot()` method.
3. The `default` routine.

Each of these accepts a routine name from the config, a `GrokBotRoutine`, or an array with `url` and `key`.

```php
// On demand, without a notifiable
Notification::route('grok-bot', 'bugs')->notify(ExceptionReported::from($e));

// A model that knows its routine
public function routeNotificationForGrokBot(Notification $notification): string
{
    return 'bugs';
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

Notification::route('grok-bot', 'bugs')->notify(ExceptionReported::from($e));

Http::assertSent(fn ($request) => $request['event'] === 'exception.reported');
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
