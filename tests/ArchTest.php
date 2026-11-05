<?php

declare(strict_types=1);

arch('source files declare strict types')
    ->expect('NotificationChannels\GrokBot')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('value objects are final')
    ->expect([
        'NotificationChannels\GrokBot\GrokBotMessage',
        'NotificationChannels\GrokBot\GrokBotRoutine',
        'NotificationChannels\GrokBot\Exceptions\CouldNotSendNotification',
    ])
    ->toBeFinal();
