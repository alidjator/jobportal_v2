<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "storage", "octane",
    |                    "session", "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'storage' => [
            'driver' => 'storage',
            'disk' => env('CACHE_STORAGE_DISK'),
            'path' => env('CACHE_STORAGE_PATH', 'framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    | Only the classes of the cached OpenAPI document (Scramble) are allowed;
    | without them the document is rebuilt on every request to /docs/api.
    | Review this list after upgrading dedoc/scramble.
    |
    */

    'serializable_classes' => [
        Dedoc\Scramble\Diagnostics\ClassContext::class,
        Dedoc\Scramble\Diagnostics\CodeLocation::class,
        Dedoc\Scramble\Diagnostics\DiagnosticSeverity::class,
        Dedoc\Scramble\Diagnostics\JsonResource\Jr001UnknownModelDiagnostic::class,
        Dedoc\Scramble\Diagnostics\PhpDoc\Pd001RedundantTypeAnnotationDiagnostic::class,
        Dedoc\Scramble\GeneratorResult::class,
        Dedoc\Scramble\Support\Generator\Combined\AllOf::class,
        Dedoc\Scramble\Support\Generator\Combined\AnyOf::class,
        Dedoc\Scramble\Support\Generator\Components::class,
        Dedoc\Scramble\Support\Generator\Header::class,
        Dedoc\Scramble\Support\Generator\MissingValue::class,
        Dedoc\Scramble\Support\Generator\OpenApi::class,
        Dedoc\Scramble\Support\Generator\Operation::class,
        Dedoc\Scramble\Support\Generator\Parameter::class,
        Dedoc\Scramble\Support\Generator\Path::class,
        Dedoc\Scramble\Support\Generator\Reference::class,
        Dedoc\Scramble\Support\Generator\RequestBodyObject::class,
        Dedoc\Scramble\Support\Generator\Response::class,
        Dedoc\Scramble\Support\Generator\Schema::class,
        Dedoc\Scramble\Support\Generator\SecurityRequirement::class,
        Dedoc\Scramble\Support\Generator\SecuritySchemes\ApiKeySecurityScheme::class,
        Dedoc\Scramble\Support\Generator\SecuritySchemes\HttpSecurityScheme::class,
        Dedoc\Scramble\Support\Generator\SecuritySchemes\Oauth2SecurityScheme::class,
        Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow::class,
        Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlows::class,
        Dedoc\Scramble\Support\Generator\SecuritySchemes\OpenIdConnectUrlSecurityScheme::class,
        Dedoc\Scramble\Support\Generator\Server::class,
        Dedoc\Scramble\Support\Generator\Tag::class,
        Dedoc\Scramble\Support\Generator\Types\ArrayType::class,
        Dedoc\Scramble\Support\Generator\Types\BooleanType::class,
        Dedoc\Scramble\Support\Generator\Types\IntegerType::class,
        Dedoc\Scramble\Support\Generator\Types\MixedType::class,
        Dedoc\Scramble\Support\Generator\Types\NullType::class,
        Dedoc\Scramble\Support\Generator\Types\NumberType::class,
        Dedoc\Scramble\Support\Generator\Types\ObjectType::class,
        Dedoc\Scramble\Support\Generator\Types\StringType::class,
        Dedoc\Scramble\Support\Generator\Types\UnknownType::class,
        Dedoc\Scramble\Support\Generator\UniqueNameOptions::class,
        Dedoc\Scramble\Support\ProNudge\ProNudgeCollector::class,
        Illuminate\Support\Collection::class,
        Src\Shared\Infrastructure\Documentation\InfoWithContact::class,
    ],

];
