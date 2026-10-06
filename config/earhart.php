<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Caching Configuration
     |--------------------------------------------------------------------------
     |
     | PropelAuth credentials belong in config/services.php under the
     | 'propelauth' key (see the README). This file holds package settings.
     |
     */

    'cache' => [
        'enabled' => env('PROPELAUTH_CACHE_ENABLED', false),
        'ttl_minutes' => env('PROPELAUTH_CACHE_TTL', 60),
    ],

    /*
     |--------------------------------------------------------------------------
     | HTTP
     |--------------------------------------------------------------------------
     |
     | Timeouts, in seconds, for requests to the PropelAuth API.
     |
     */

    'http' => [
        'timeout' => env('PROPELAUTH_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('PROPELAUTH_HTTP_CONNECT_TIMEOUT', 10),
    ],

    /*
     |--------------------------------------------------------------------------
     | Rate-limit retries
     |--------------------------------------------------------------------------
     |
     | How often a request that receives a 429 is retried. Waits honour
     | PropelAuth's Retry-After header, otherwise back off exponentially from
     | base_delay_ms. No wait exceeds max_delay_ms: if Retry-After asks for
     | longer, the RateLimitException is thrown straight away.
     |
     | Set times to 0 to fail fast (e.g. in web requests) and let queued jobs
     | retry instead. Values are read per request, so they can be changed at
     | runtime with config().
     |
     */

    'retries' => [
        'times' => env('PROPELAUTH_RETRY_TIMES', 2),
        'base_delay_ms' => env('PROPELAUTH_RETRY_BASE_DELAY_MS', 2000),
        'max_delay_ms' => env('PROPELAUTH_RETRY_MAX_DELAY_MS', 5000),
    ],

    /*
     |--------------------------------------------------------------------------
     | Access token verification
     |--------------------------------------------------------------------------
     |
     | Access tokens are verified locally against your environment's public
     | key. By default it is fetched once from PropelAuth and cached for
     | cache_minutes. Set verifier_key (the PEM from the Backend Integration
     | page; "\n" escapes are accepted) to skip that request. issuer defaults
     | to services.propelauth.auth_url.
     |
     */

    'token_verification' => [
        'verifier_key' => env('PROPELAUTH_VERIFIER_KEY'),
        'issuer' => env('PROPELAUTH_ISSUER'),
        'cache_minutes' => env('PROPELAUTH_VERIFIER_KEY_CACHE_MINUTES', 1440),
    ],
];
