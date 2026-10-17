<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Company Invitation Link
    |--------------------------------------------------------------------------
    |
    | The client page that accepts a company invitation, as a URL template where
    | `{token}` stands for the invitation token, for example
    | "https://careers.example.com/invitations/{token}". This API has no UI of
    | its own; when the template is empty, invitations cannot be created.
    |
    */

    'invitation_url' => env('COMPANY_INVITATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Psychologist Invitation Link
    |--------------------------------------------------------------------------
    |
    | The client page that accepts a psychologist's invitation, as a URL template
    | where `{token}` stands for the invitation token, for example
    | "https://careers.example.com/provider/invitations/{token}". When the template
    | is empty, psychologists cannot be invited.
    |
    */

    'psychologist_invitation_url' => env('PSYCHOLOGIST_INVITATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Campaign registration link
    |--------------------------------------------------------------------------
    |
    | The client page where a person registers through a campaign, with `{code}` standing for the campaign code (the legacy format is `https://your-site.example/{code}/register`). The API only builds the link (and its QR
    | code); when the template is empty, campaigns work but carry no link.
    |
    */

    'campaign_registration_url' => env('CAMPAIGN_REGISTRATION_URL'),

    // Client page of the job seeker's own profile, linked from the profile reminder email. Empty: the email carries no link.
    'profile_url' => env('JOB_SEEKER_PROFILE_URL'),

    // Client page of an application for the job seeker, with {application_id} in it; the calendar file of an interview links to it. Empty: the file carries no link.
    'application_url' => env('JOB_SEEKER_APPLICATION_URL'),

    // Client pages of the newsletter: URL templates where {token} stands for the token. Either empty means the newsletter is off (503 `newsletter-unavailable`).
    'newsletter_confirm_url' => env('NEWSLETTER_CONFIRM_URL'),
    'newsletter_unsubscribe_url' => env('NEWSLETTER_UNSUBSCRIBE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
