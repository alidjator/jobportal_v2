<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        // OAuth client IDs (web, Android, iOS) whose ID tokens are accepted, comma separated. Empty means Google sign-in is off.
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))))),
    ],

    'moodle' => [
        // The training platform (Moodle web service): the server URL (`.../webservice/rest/server.php`), a token with read access, and the course page template
        // where `{id}` stands for the course id. Any of them empty means the training endpoints answer 503.
        'url' => env('MOODLE_BASE_URL'),
        'token' => env('MOODLE_TOKEN'),
        'course_url' => env('MOODLE_COURSE_URL'),
    ],

    'janus' => [
        // The self-hosted video server (`docs/design/phase-7-tele-interview.md`, form 1). `http_url` is its HTTP API and `admin_url` its Admin API (both private,
        // on the Docker network), `api_token` the token this application sends to the HTTP API (which runs with `token_auth`), `ws_url` the public WSS address
        // browsers connect to, and `webhook_secret` the secret its event handler sends to the internal webhooks. Any of the first four empty means video joins answer 503.
        'http_url' => env('JANUS_HTTP_URL'),
        'admin_url' => env('JANUS_ADMIN_URL'),
        'admin_secret' => env('JANUS_ADMIN_SECRET'),
        'api_token' => env('JANUS_API_TOKEN'),
        'ws_url' => env('JANUS_WS_URL'),
        'webhook_secret' => env('JANUS_WEBHOOK_SECRET'),
        // Recordings: the video server writes the raw tracks to a volume it shares with this application (`recordings_dir` is that volume as the video container
        // sees it, `recordings_path` as this application sees it), and `converter_url` is the small converter service next to it. The converter and the event
        // handler authenticate with `webhook_secret` in both directions. Recording needs all three.
        'converter_url' => env('JANUS_CONVERTER_URL'),
        'recordings_dir' => env('JANUS_RECORDINGS_DIR'),
        'recordings_path' => env('JANUS_RECORDINGS_PATH'),
    ],

    'asterisk' => [
        // The voice server for phone interviews (`docs/design/phase-7-tele-interview.md`, form 3). `ari_url` is its REST interface (private, on the Docker
        // network, for example `http://asterisk:8088/ari`) with the account `ari_user`/`ari_password`; `wss_url` the public WSS address team browsers connect
        // to and `sip_domain` the domain they register with (the host of `wss_url` when empty). `caller_id` is the platform number (a number of the SIP trunk)
        // every candidate sees when called, and `trunk_endpoint` the name of the trunk's PJSIP endpoint calls leave through. Any of the first four empty means phone calls answer 503.
        'ari_url' => env('ASTERISK_ARI_URL'),
        'ari_user' => env('ASTERISK_ARI_USER'),
        'ari_password' => env('ASTERISK_ARI_PASSWORD'),
        'wss_url' => env('ASTERISK_WSS_URL'),
        'sip_domain' => env('ASTERISK_SIP_DOMAIN'),
        'caller_id' => env('VOICE_CALLER_ID'),
        'trunk_endpoint' => env('ASTERISK_TRUNK_ENDPOINT', 'trunk'),
    ],

    'turn' => [
        // The coturn server (`use-auth-secret`): its URLs, comma separated (`turn:host:3478?transport=udp,turns:host:5349`), and the shared secret.
        'urls' => env('TURN_URLS'),
        'secret' => env('TURN_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
