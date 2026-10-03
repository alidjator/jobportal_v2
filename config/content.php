<?php

return [
    // Base URL aplikasi web lama (frontend); dipakai oleh /page-list.
    'website_url' => env('CONTENT_WEBSITE_URL', env('APP_URL', 'http://localhost')),

    // Setara config('captcha.*') di aplikasi lama (anhskohbo/no-captcha).
    'captcha' => [
        'active' => (bool) env('CONTENT_CAPTCHA_ACTIVE', false),
        'secret' => env('CONTENT_CAPTCHA_SECRET'),
    ],
];
