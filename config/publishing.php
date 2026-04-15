<?php
return [
    'deploy_strategy' => env('DEPLOY_STRATEGY', 'auto'),
    'public_path' => env('PUBLISH_PATH', public_path('sites')),
    'staging_path' => storage_path('app/builds'),
    'rollback_path' => storage_path('app/rollback'),
    'max_retained_builds' => 5,
];
