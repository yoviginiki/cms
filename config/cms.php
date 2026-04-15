<?php

return [

    'redis_enabled' => env('REDIS_ENABLED', false),

    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'db_default' => env('REDIS_DB_DEFAULT', 0),
        'db_cache' => env('REDIS_DB_CACHE', 1),
        'db_session' => env('REDIS_DB_SESSION', 2),
        'db_queue' => env('REDIS_DB_QUEUE', 3),
        'prefix' => env('REDIS_PREFIX', 'cms_'),
    ],

    'drivers' => [
        'cache' => env('REDIS_ENABLED', false) ? 'redis' : 'file',
        'session' => env('REDIS_ENABLED', false) ? 'redis' : 'database',
        'queue' => env('REDIS_ENABLED', false) ? 'redis' : 'database',
        'broadcast' => env('REDIS_ENABLED', false) ? 'reverb' : null,
    ],

];
