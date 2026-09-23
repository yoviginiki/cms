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

    // Host names / shared-root folders that can never become a site's deploy
    // target (F03). The admin host (APP_URL) and Sanctum stateful domains are
    // always added by DeployTargetResolver on top of this list.
    'reserved_domains' => array_values(array_filter(array_map('trim', explode(',',
        (string) env('PUBLISH_RESERVED_DOMAINS', 'sys.ensodo.eu,admin.ensodo.eu,api.ensodo.eu')
    )))),
    'reserved_slugs' => ['sys', 'admin', 'api', 'login', 'register'],

    // SSH deploy keys (H01): a site may only reference key files inside this
    // operator-managed directory — never an arbitrary server path.
    'ssh_keys_path' => env('PUBLISH_SSH_KEYS_PATH', storage_path('app/ssh-keys')),

    // Relative paths inside a custom-domain docroot that a full deploy's
    // prune must never remove (they live outside the CMS build).
    'preserve_paths' => ['themes'],

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
