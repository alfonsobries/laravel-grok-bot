<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification;
use NotificationChannels\GrokBot\GrokBotChannel;
use NotificationChannels\GrokBot\GrokBotMessage;
use NotificationChannels\GrokBot\GrokBotRoutine;
use NotificationChannels\GrokBot\Tests\Fixtures\ScraperFailed;
use NotificationChannels\GrokBot\Tests\Fixtures\Team;

const STARTED = ['success' => true, 'runUuid' => 'run-1'];

function sent(): Request
{
    $requests = Http::recorded();
    expect($requests)->toHaveCount(1);

    return $requests[0][0];
}

it('posts the message to the default routine with the bearer key', function () {
    Http::fake(['*' => Http::response(STARTED)]);

    Notification::route('grok-bot', null)->notify(new ScraperFailed(
        fn () => GrokBotMessage::create('Scraper marcate failed')->data(['repo' => 'marcate'])->with('attempts', 3),
    ));

    $request = sent();
    expect($request->url())->toBe('https://api2.cursor.sh/automations/webhook/default-routine')
        ->and($request->method())->toBe('POST')
        ->and($request->header('Authorization'))->toBe(['Bearer crsr_default'])
        ->and($request->header('Content-Type'))->toBe(['application/json'])
        ->and($request->data())->toBe(['message' => 'Scraper marcate failed', 'repo' => 'marcate', 'attempts' => 3]);
});

it('routes to a named routine from an on-demand route', function () {
    Http::fake(['*' => Http::response(STARTED)]);

    Notification::route('grok-bot', 'alerts')->notify(new ScraperFailed(fn () => 'hello'));

    expect(sent()->url())->toEndWith('/alerts-routine')
        ->and(sent()->header('Authorization'))->toBe(['Bearer crsr_alerts'])
        ->and(sent()->data())->toBe(['message' => 'hello']);
});

it('routes through the notifiable', function (mixed $route, string $url, string $authorization) {
    Http::fake(['*' => Http::response(STARTED)]);

    (new Team($route))->notify(new ScraperFailed(fn () => ['event' => 'scraper.failed']));

    expect(sent()->url())->toBe($url)
        ->and(sent()->header('Authorization'))->toBe([$authorization])
        ->and(sent()->data())->toBe(['event' => 'scraper.failed']);
})->with([
    'routine name' => ['alerts', 'https://api2.cursor.sh/automations/webhook/alerts-routine', 'Bearer crsr_alerts'],
    'routine object' => [GrokBotRoutine::make('https://example.test/hook', 'bearer  crsr_x'), 'https://example.test/hook', 'Bearer crsr_x'],
    'url and key array' => [['url' => 'https://example.test/array', 'key' => 'crsr_y'], 'https://example.test/array', 'Bearer crsr_y'],
    'no route' => [null, 'https://api2.cursor.sh/automations/webhook/default-routine', 'Bearer crsr_default'],
]);

it('lets the message pick the routine over the notifiable', function () {
    Http::fake(['*' => Http::response(STARTED)]);

    (new Team('default'))->notify(new ScraperFailed(fn () => GrokBotMessage::create('x')->routine('alerts')));

    expect(sent()->url())->toEndWith('/alerts-routine');
});

it('returns the run from the channel', function () {
    Http::fake(['*' => Http::response(STARTED)]);

    $result = app(GrokBotChannel::class)->send(new Team, new ScraperFailed(fn () => 'x'));

    expect($result)->toBe(STARTED);
});

it('sends an empty JSON object when the message has nothing in it', function () {
    Http::fake(['*' => Http::response(STARTED)]);

    (new Team)->notify(new ScraperFailed(fn () => GrokBotMessage::create()));

    expect(sent()->body())->toBe('{}');
});

it('sends nothing when the notification returns null or has no toGrokBot', function () {
    Http::fake();

    (new Team)->notify(new ScraperFailed(fn () => null));
    $result = app(GrokBotChannel::class)->send(new Team, new class extends BaseNotification {});

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('rejects a message of the wrong type', function () {
    Http::fake();

    (new Team)->notify(new ScraperFailed(fn () => 42));
})->throws(CouldNotSendNotification::class, 'toGrokBot() must return a GrokBotMessage, an array or a string, int given.');

it('explains each refusal and fires NotificationFailed', function (int $status, mixed $body, string $reason) {
    Event::fake([NotificationFailed::class]);
    Http::fake(['*' => Http::response($body, $status)]);

    try {
        Notification::route('grok-bot', 'alerts')->notify(new ScraperFailed(fn () => 'x'));
        $this->fail('Expected an exception');
    } catch (CouldNotSendNotification $exception) {
        expect($exception->getMessage())->toContain("routine `alerts` did not start a run ({$status}): {$reason}")
            ->and($exception->getMessage())->not->toContain('crsr_')
            ->and($exception->status)->toBe($status);
    }

    Event::assertDispatched(NotificationFailed::class, fn (NotificationFailed $event): bool => $event->channel === 'grok-bot'
        && ($event->data['routine'] ?? null) === 'alerts'
        && $event->data['status'] === $status
        && $event->data['exception'] instanceof CouldNotSendNotification);
})->with([
    'invalid key' => [401, ['code' => 'error', 'message' => 'Invalid API key'], 'the key is invalid or was regenerated. Invalid API key'],
    'paused routine' => [400, ['success' => false, 'error' => 'Automation abc is disabled'], 'the routine is not active. Automation abc is disabled'],
    'deleted routine' => [404, 'Not Found', 'the webhook URL no longer exists. Not Found'],
    'rate limit' => [429, ['success' => false, 'error' => 'Automation run rate limit exceeded.'], 'the routine hit its run rate limit'],
    'rate limit as 400' => [400, ['success' => false, 'error' => 'Automation run rate limit exceeded.'], 'the routine hit its run rate limit'],
    'server error' => [503, '', 'Grok Bot had an error'],
    'other refusal' => [422, ['message' => 'Bad body'], 'the request was refused. Bad body'],
    '200 without success' => [200, ['success' => false], 'the request was refused.'],
]);

it('retries connection errors and server errors but not refusals', function () {
    Http::fake(['*' => Http::sequence()->pushStatus(502)->pushStatus(503)->push(STARTED)]);

    (new Team)->notify(new ScraperFailed(fn () => 'x'));
    Http::assertSentCount(3);
});

it('does not retry a refused key', function () {
    Http::fake(['*' => Http::sequence()->push(['message' => 'Invalid API key'], 401)->push(STARTED)]);

    expect(fn () => (new Team)->notify(new ScraperFailed(fn () => 'x')))->toThrow(CouldNotSendNotification::class);
    Http::assertSentCount(1);
});

it('reports a routine it could not reach', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

    expect(fn () => (new Team)->notify(new ScraperFailed(fn () => 'x')))
        ->toThrow(CouldNotSendNotification::class, 'Could not reach Grok Bot routine `default`: cURL error 6: Could not resolve host');
});

it('names the missing config', function (array $routines, string $route, string $error) {
    config()->set('grok-bot.routines', $routines);
    Http::fake();

    expect(fn () => Notification::route('grok-bot', $route)->notify(new ScraperFailed(fn () => 'x')))
        ->toThrow(CouldNotSendNotification::class, $error);
    Http::assertNothingSent();
})->with([
    'unknown routine' => [[], 'alerts', 'Grok Bot routine `alerts` is not configured. Add it under `grok-bot.routines`.'],
    'no url' => [['alerts' => ['url' => '', 'key' => 'k']], 'alerts', 'Grok Bot routine `alerts` has no url.'],
    'no key' => [['alerts' => ['url' => 'https://example.test', 'key' => null]], 'alerts', 'Grok Bot routine `alerts` has no key.'],
]);

it('labels a runtime routine by its path so the key never shows', function () {
    Http::fake(['*' => Http::response(['message' => 'Invalid API key'], 401)]);

    expect(fn () => (new Team(GrokBotRoutine::make('https://api2.cursor.sh/automations/webhook/abc', 'crsr_secret')))->notify(new ScraperFailed(fn () => 'x')))
        ->toThrow(CouldNotSendNotification::class, 'routine `/automations/webhook/abc`');
});

it('fires NotificationFailed once, with the routine', function () {
    $events = [];
    Event::listen(NotificationFailed::class, function (NotificationFailed $event) use (&$events): void {
        $events[] = $event;
    });
    Http::fake(['*' => Http::response(['message' => 'Invalid API key'], 401)]);

    expect(fn () => Notification::route('grok-bot', 'alerts')->notify(new ScraperFailed(fn () => 'x')))->toThrow(CouldNotSendNotification::class);
    expect($events)->toHaveCount(1)
        ->and($events[0]->data['routine'])->toBe('alerts');
});
