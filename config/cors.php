<?php

$allowedOrigins = [];

if (env('APP_ENV') == 'local') {
    // Running on local dev server
    $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:8081',
    ];
} else {
    // Live / production
    $allowedOrigins = [
        'http://zimaboard.zmwl.local',
        'http://localhost:8081',
    ];
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
