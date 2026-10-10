<?php

return [
    /*
     * The baseline request limit of the whole API (`ApiRateLimit`), per minute: per account when the request carries a valid token,
     * per IP address otherwise. The named limiters of the contexts (sign-in, search, chat...) apply on top of it.
     */
    'rate_limit' => [
        'user' => (int) env('API_RATE_LIMIT_USER', 120),
        'guest' => (int) env('API_RATE_LIMIT_GUEST', 60),
    ],
];
