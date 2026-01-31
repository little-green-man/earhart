<?php

return [
    /*
     |--------------------------------------------------------------------------
     | PropelAuth Configuration
     |--------------------------------------------------------------------------
     */
    'propelauth' => [
        'client_id' => env('PROPELAUTH_CLIENT_ID'),
        'client_secret' => env('PROPELAUTH_CLIENT_SECRET'),
        'auth_url' => env('PROPELAUTH_AUTH_URL'),
        'api_key' => env('PROPELAUTH_API_KEY'),
        'redirect_url' => env('PROPELAUTH_CALLBACK_URL'),
        'svix_secret' => env('PROPELAUTH_SVIX_SECRET'),
    ],
];
