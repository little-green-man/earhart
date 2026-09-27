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
];
