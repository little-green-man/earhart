<?php

return [
    /*
     |--------------------------------------------------------------------------
     | PropelAuth Configuration
     |--------------------------------------------------------------------------
     */

    'client_id' => env('PROPELAUTH_CLIENT_ID'),
    'client_secret' => env('PROPELAUTH_CLIENT_SECRET'),
    'auth_url' => env('PROPELAUTH_AUTH_URL'),
    'api_key' => env('PROPELAUTH_API_KEY'),
    'redirect_url' => env('PROPELAUTH_CALLBACK_URL'),
    'svix_secret' => env('PROPELAUTH_SVIX_SECRET'),

    /*
     |--------------------------------------------------------------------------
     | Caching Configuration
     |--------------------------------------------------------------------------
     */

    'cache' => [
        'enabled' => env('PROPELAUTH_CACHE_ENABLED', false),
        'ttl_minutes' => env('PROPELAUTH_CACHE_TTL', 60),
    ],

    /*
     |--------------------------------------------------------------------------
     | Feature Flags
     |--------------------------------------------------------------------------
     */

    'features' => [
        'event_enrichment' => true,
        'error_reporting' => true,
        'automatic_cache_invalidation' => true,
    ],
];
