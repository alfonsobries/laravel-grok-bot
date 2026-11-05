<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('pings the default routine', function () {
    Http::fake(['*' => Http::response(['success' => true, 'runUuid' => 'run-9'])]);

    $this->artisan('grok-bot:ping')
        ->expectsOutputToContain('Routine `default` started run run-9.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request['type'] === 'ping' && str_contains($request['message'], 'No action needed'));
});

it('sends a custom message to a named routine', function () {
    Http::fake(['*' => Http::response(['success' => true])]);

    $this->artisan('grok-bot:ping', ['routine' => 'alerts', '--message' => 'Hola'])
        ->expectsOutputToContain('started run (no id)')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/alerts-routine') && $request->data() === ['message' => 'Hola']);
});

it('fails with the reason', function () {
    Http::fake(['*' => Http::response(['code' => 'error', 'message' => 'Invalid API key'], 401)]);

    $this->artisan('grok-bot:ping', ['routine' => 'alerts'])
        ->expectsOutputToContain('the key is invalid or was regenerated')
        ->assertFailed();
});
