<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification;
use NotificationChannels\GrokBot\Tests\Fixtures\ExceptionReported;

beforeEach(function () {
    config()->set('grok-bot.routines.bugs', ['url' => 'https://api2.cursor.sh/automations/webhook/bugs-routine', 'key' => 'crsr_bugs']);
    config()->set('app.commit', '4f5177f');

    // The README's bootstrap/app.php reporter, without the production check.
    app(ExceptionHandler::class)->reportable(function (Throwable $e): void {
        if (! $e instanceof CouldNotSendNotification) {
            Notification::route('grok-bot', 'bugs')->notify(ExceptionReported::from($e));
        }
    });
});

it('hands a reported exception to the bugs routine with what the Bot needs', function () {
    Http::fake(['*' => Http::response(['success' => true, 'runUuid' => 'run-1'])]);

    $exception = new ErrorException('Undefined array key "currency"');
    report($exception);

    Http::assertSent(function (Request $request) use ($exception): bool {
        return str_ends_with($request->url(), '/bugs-routine')
            && $request['message'] === 'ErrorException: Undefined array key "currency"'
            && $request['event'] === 'exception.reported'
            && $request['repository'] === 'acme/shop'
            && $request['commit'] === '4f5177f'
            && $request['location'] === $exception->getFile().':'.$exception->getLine()
            && $request['url'] === null
            && str_starts_with($request['trace'], '#0 ');
    });
});

it('does not report the channel failing back to the routine', function () {
    Http::fake();

    report(CouldNotSendNotification::routineNotConfigured('bugs'));

    Http::assertNothingSent();
});

it('keeps the trace within the body budget', function () {
    $notification = ExceptionReported::from(new RuntimeException('Boom'));

    expect(mb_strlen($notification->trace))->toBeLessThanOrEqual(4000);
});
