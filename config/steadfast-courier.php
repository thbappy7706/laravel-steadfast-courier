<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SteadFast API Base URL
    |--------------------------------------------------------------------------
    |
    | All requests are sent to this URL. You only need to change it when
    | SteadFast provides a different endpoint or you use a proxy.
    |
    */

    'base_url' => env('STEADFAST_BASE_URL', 'https://portal.steadfast.com.bd/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Your API key and secret key from the SteadFast merchant panel. They are
    | intentionally null by default: the package fails loudly with a
    | ConfigurationException instead of sending placeholder credentials.
    |
    */

    'api_key' => env('STEADFAST_API_KEY'),

    'secret_key' => env('STEADFAST_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Set webhook_bearer_token to the token configured in the SteadFast
    | panel. The webhook route is opt-in: enable it when you want the
    | package to receive delivery status callbacks for you.
    |
    */

    'webhook_bearer_token' => env('STEADFAST_BEARER_TOKEN'),

    'webhook' => [
        'enabled' => (bool) env('STEADFAST_WEBHOOK_ENABLED', false),
        'path' => env('STEADFAST_WEBHOOK_PATH', 'steadfast/webhook'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | Timeouts are in seconds. Retries only apply to idempotent GET requests
    | and to connection failures or 429/5xx responses; order creation is
    | never retried because that could create duplicate consignments.
    |
    */

    'timeout' => (int) env('STEADFAST_TIMEOUT', 15),

    'connect_timeout' => (int) env('STEADFAST_CONNECT_TIMEOUT', 5),

    'retry' => [
        'times' => (int) env('STEADFAST_RETRY_TIMES', 2),
        'sleep_ms' => (int) env('STEADFAST_RETRY_SLEEP_MS', 200),
    ],

    'bulk_chunk_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, each API call is logged with its method, URL and status.
    | Request headers and bodies are never logged, so credentials cannot leak.
    |
    */

    'logging' => [
        'enabled' => (bool) env('STEADFAST_LOGGING', false),
        'channel' => env('STEADFAST_LOG_CHANNEL'),
    ],

];
