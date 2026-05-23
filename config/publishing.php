<?php
return [
    'deploy_strategy' => env('DEPLOY_STRATEGY', 'auto'),
    'public_path' => env('PUBLISH_PATH', public_path('sites')),
    'staging_path' => storage_path('app/builds'),
    'rollback_path' => storage_path('app/rollback'),
    'max_retained_builds' => 5,

    // Base path for tenant site deployments (each domain gets its own public_html)
    // Pattern: {tenant_base}/{domain}/public_html
    'tenant_base' => env('TENANT_BASE_PATH', '/home/cytechno/web'),
];
