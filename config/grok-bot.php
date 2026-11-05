<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default routine
    |--------------------------------------------------------------------------
    |
    | The routine a notification goes to when neither the message nor the
    | notifiable names one.
    |
    */

    'default' => env('GROK_BOT_ROUTINE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Routines
    |--------------------------------------------------------------------------
    |
    | Each Grok Bot routine with a webhook trigger, by name. Copy the "POST to"
    | URL and the key from the routine's Webhook section. The key may be given
    | with or without its "Bearer " prefix.
    |
    */

    'routines' => [
        'default' => [
            'url' => env('GROK_BOT_WEBHOOK_URL'),
            'key' => env('GROK_BOT_WEBHOOK_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for Grok Bot, and how many times to retry a request that
    | could not connect or got a 5xx. A 4xx is never retried.
    |
    */

    'timeout' => (int) env('GROK_BOT_TIMEOUT', 10),

    'retries' => (int) env('GROK_BOT_RETRIES', 2),

    'retry_delay_ms' => (int) env('GROK_BOT_RETRY_DELAY_MS', 500),

];
