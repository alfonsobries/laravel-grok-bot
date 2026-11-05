<?php

declare(strict_types=1);

use NotificationChannels\GrokBot\GrokBotMessage;

it('builds the body with message first and data next to it', function () {
    $message = GrokBotMessage::create('first')->message('Scraper failed')->data(['repo' => 'marcate', 'attempts' => 1])->data(['attempts' => 3]);

    expect($message->toArray())->toBe(['message' => 'Scraper failed', 'repo' => 'marcate', 'attempts' => 3])
        ->and(json_encode($message))->toBe('{"message":"Scraper failed","repo":"marcate","attempts":3}');
});

it('leaves message out when there is none', function () {
    expect(GrokBotMessage::create()->with('event', 'ping')->toArray())->toBe(['event' => 'ping'])
        ->and(GrokBotMessage::create()->getRoutine())->toBeNull();
});
