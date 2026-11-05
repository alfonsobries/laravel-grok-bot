---
name: grok-bot-notifications
description: Wires a Laravel app's events to a Grok Bot routine with alfonsobries/laravel-grok-bot - picks or creates the routine with the user, gets its webhook URL and key into .env and config/grok-bot.php, writes the notification with toGrokBot(), pings the routine and tests it. Use when the user wants a Bot to resolve, investigate or be told about something that happens in the app (a production exception, a support ticket, a failed payment or job, a suspicious order, a daily report), mentions Grok Bot, a Bot routine, a routine webhook, "avísale al bot", "que le llegue al bot", or adds the grok-bot channel to a notification. Not for Slack, Telegram, mail or database notifications, and not for building the Bot's own behaviour inside Grok Bot.
license: MIT
metadata:
  author: Alfonso Bribiesca
---

# Grok Bot notifications

A Grok Bot routine with a webhook trigger starts a run each time it receives a POST. The app sends the facts as JSON; the routine's instruction decides what the Bot does with them. Keep that split: the app never decides how the Bot reacts.

## 1. Agree on the routine

Ask the user, in one message:

1. Which Bot receives these notifications, an existing one or a new one.
2. What the Bot does with each one: resolve it, investigate, tell the user, ask before acting. This becomes the routine's instruction.

The user creates the routine in Grok Bot (ask the Bot to add a webhook trigger to a routine), writes that instruction, sets it **Active**, and copies two values from the routine's **Webhook** section on desktop: the **POST to** URL and the **key**. If they ask for help with the instruction, draft it: what the JSON contains, what to do with it, which actions need the user's OK, who receives the result, and to ignore `{"type":"ping"}` bodies. For example:

```text
An exception from production arrives as JSON. Find the cause in the
GitHub repo named in "repository" at "commit". If it is a bug in our code,
write a failing test, fix it, and open a draft pull request; send me the
link here. If it is not (a third-party outage, a bad deploy, a bot hitting
a bad URL), tell me in one line and do nothing else.
Ignore bodies with "type": "ping".
```

Never ask the user to paste the key into the chat when they can write it into `.env` themselves.

## 2. Configure

```bash
composer require alfonsobries/laravel-grok-bot
php artisan vendor:publish --tag=grok-bot-config   # only when adding a routine besides `default`
```

`.env` (and the production environment):

```dotenv
GROK_BOT_WEBHOOK_URL=https://api2.cursor.sh/automations/webhook/<id>
GROK_BOT_WEBHOOK_KEY=crsr_...
```

One routine per purpose. A second routine gets its own name and keys in `config/grok-bot.php`:

```php
'routines' => [
    'default' => ['url' => env('GROK_BOT_WEBHOOK_URL'), 'key' => env('GROK_BOT_WEBHOOK_KEY')],
    'bugs' => ['url' => env('GROK_BOT_BUGS_WEBHOOK_URL'), 'key' => env('GROK_BOT_BUGS_WEBHOOK_KEY')],
],
```

Then `php artisan grok-bot:ping <routine>` must report a started run. On failure the message names the cause (invalid key, routine not active, rate limit).

## 3. Write the notification

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

- `message` is one readable line; facts go in `data` as flat keys with stable names. Give the Bot what it needs to act (ids, links, the command to retry), not what it can look up itself and nothing it should not see: no secrets, tokens or personal data beyond what the task needs.
- Queue the notification (`ShouldQueue`): the webhook is an HTTP call.
- A routine runs at most 200 times an hour. Batch or debounce events that can burst instead of sending one per occurrence.
- The channel throws `CouldNotSendNotification` and fires `NotificationFailed` when the routine refuses. Let it fail the job so it is retried and visible, and never report that exception back to a routine.

## 4. Test

```php
Http::fake(['api2.cursor.sh/*' => Http::response(['success' => true, 'runUuid' => 'run-1'])]);

Notification::route('grok-bot', 'bugs')->notify(ExceptionReported::from(new RuntimeException('Boom')));

Http::assertSent(fn ($request) => $request['event'] === 'exception.reported' && $request['message'] === 'RuntimeException: Boom');
```

Finish with a real `grok-bot:ping` against production config and confirm with the user that the Bot reacted as its instruction says.
