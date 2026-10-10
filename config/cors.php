<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma separated origins from `.env`, e.g. `https://karir.example,https://admin.example`. Empty allows no cross-origin browser call.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After', 'Content-Language'],

    'max_age' => 600,

    // Authentication uses bearer tokens, never cookies.
    'supports_credentials' => false,
];
