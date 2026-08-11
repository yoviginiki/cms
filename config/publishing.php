<?php
return [
    'deploy_strategy' => env('DEPLOY_STRATEGY', 'auto'),
    'public_path' => env('PUBLISH_PATH', public_path('sites')),
    // Env-overridable so the TEST SUITE stages builds in its own sandbox.
    // With a shared staging dir, test publishes flood it and BuildRetention
    // (which scans the test-sandboxed public_path for live symlinks, finding
    // none) prunes builds that PRODUCTION symlinks still point at — this
    // took down live sites on 2026-07-22. Relative overrides are resolved
    // against base_path so symlink/copy deploys get absolute targets.
    'staging_path' => ($p = env('PUBLISH_STAGING_PATH'))
        ? (str_starts_with($p, '/') ? $p : base_path($p))
        : storage_path('app/builds'),
    'rollback_path' => ($p = env('PUBLISH_ROLLBACK_PATH'))
        ? (str_starts_with($p, '/') ? $p : base_path($p))
        : storage_path('app/rollback'),
    'max_retained_builds' => 5,

    // Base path for tenant site deployments (each domain gets its own public_html)
    // Pattern: {tenant_base}/{domain}/public_html
    'tenant_base' => env('TENANT_BASE_PATH', '/home/cytechno/web'),

    // Parallel post rendering (opt-in). When enabled, a full publish of a site
    // with more than parallel_chunk_size posts fans the post rendering out across
    // worker processes (Bus::batch), then re-enters PublishSiteJob to build
    // pages + finalize + deploy. The resumable posts loop makes that re-run skip
    // the already-rendered posts. OFF by default — the serial path is unchanged
    // unless this is explicitly turned on. Ignored for projection-enabled sites
    // (they must re-emit every sidecar for the manifest in a single pass) and in
    // the sync queue driver.
    'parallel_posts' => (bool) env('PUBLISH_PARALLEL_POSTS', false),
    'parallel_chunk_size' => (int) env('PUBLISH_PARALLEL_CHUNK_SIZE', 400),
];
