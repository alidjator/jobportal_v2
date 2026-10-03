<?php

return [
    // Nama env dipertahankan sama dengan aplikasi lama agar .env bisa dipakai ulang.
    'default_language' => env('APP_DEFAULT_LANGUAGE', 'en'),
    'default_currency' => env('APP_CURRENCY', 'USD'),
    'default_currency_symbol' => env('APP_CURRENCY_SYMBOL', '$'),
];
