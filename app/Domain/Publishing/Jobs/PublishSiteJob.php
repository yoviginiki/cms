<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Publishing\Support\RedirectRules;
use Illuminate\Support\Facades\Log;
use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Exceptions\NonRetryableBuildException;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\DeploymentGate;
use App\Domain\Publishing\Services\SeoService;
use App\Domain\Publishing\Services\RssFeedGenerator;
use App\Domain\Publishing\Services\SitemapGenerator;
use App\Domain\Publishing\Services\RobotsGenerator;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Domain\Menus\Services\MenuRenderer;
use App\Events\DeploymentProgressEvent;
use App\Models\Deployment;
use App\Models\Grid;
use App\Models\Page;
use App\Models\Site;
use App\Models\PageVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class PublishSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Retries are the convergence mechanism for very large sites: each try
    // resumes where the last left off (posts already rendered this deployment
    // are skipped), so a build that can't finish in one timeout window still
    // completes across a few passes instead of restarting forever.
    public int $tries = 8;
    // Large content sites (thousands of posts) build for many minutes; the old
    // 300s cap timed out mid-build and retried from scratch. Sized well above
    // the worst-case full build.
    public int $timeout = 3600;
    // F16: transient failures wait before the next pass (seconds).
    public array $backoff = [30, 90, 180];

    /**
     * Prevent a second worker from running the SAME deployment concurrently.
     * The redis queue's retry_after (90s) is far below this job's runtime, so a
     * long build gets re-made-visible and re-popped; without this guard two
     * workers would publish into the same staging dir. dontRelease() discards
     * the duplicate rather than requeuing it. Sequential re-pops (after the job
     * finishes) are caught by the terminal-status guard in handle().
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->deploymentId))
            ->dontRelease()
            ->expireAfter($this->timeout + 120)];
    }

    public string $deploymentId;
    public ?string $rollbackTargetId;
    public string $tenantId;
    public Deployment $deployment;

    public function __construct(
        Deployment $deployment,
        public string $type = 'partial',
        ?Deployment $rollbackTarget = null,
    ) {
        // Store IDs instead of models to avoid RLS issues during deserialization
        $this->deploymentId = $deployment->id;
        $this->rollbackTargetId = $rollbackTarget?->id;
        $this->tenantId = $deployment->site->tenant_id;
        // F16: long builds run on the connection whose retry_after exceeds
        // this job's timeout (config/queue.php 'builds').
        $this->onConnection('builds');
    }

    /**
     * Restore models manually with RLS context set.
     */
    public function restoreModels(): void
    {
        // Set RLS context for PostgreSQL
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");
    }

    public function handle(
        BuildPageService $buildService,
        DeployService $deployService,
        SitemapGenerator $sitemapGenerator,
        RobotsGenerator $robotsGenerator,
    ): void {
        $this->restoreModels();

        $this->deployment = Deployment::findOrFail($this->deploymentId);

        // Guard against a redis re-pop running an already-finished deployment a
        // second time (retry_after < timeout). If this deployment is already in
        // a terminal state, or a newer deployment exists for the site, this is a
        // stale duplicate — do nothing.
        if (in_array($this->deployment->status, ['live', 'failed', 'rolled_back', 'cancelled'], true)) {
            return;
        }

        $site = $this->deployment->site;
        $site->load('theme');
        // Via config, NOT a hardcoded storage_path: the test suite sandboxes
        // publishing.staging_path — hardcoding it made tests stage into the
        // production builds dir (and retention then pruned live builds).
        $stagingPath = rtrim(config('publishing.staging_path'), '/') . "/{$this->deployment->id}";

        // F15/F16: the worker is alive — heartbeat + attempt bookkeeping. The
        // reaper looks at the heartbeat, not the row's age.
        $this->deployment->update(['started_at' => $this->deployment->started_at ?? now()]);
        DeploymentGate::heartbeat($this->deployment, ['attempt' => $this->attempts()]);

        try {
            // Rollback (FIX-B6b): re-point the live site to a prior deployment's
            // build instead of rebuilding current DB content. Previously the job
            // ignored the target and republished today's content (a silent no-op).
            if ($this->type === 'rollback' && $this->rollbackTargetId) {
                $target = Deployment::find($this->rollbackTargetId);
                // F18: the target's ARTIFACT (a full release view — for a delta
                // deployment that is {id}-release, never its partial staging dir).
                $targetBuild = $target?->artifact_path ?: rtrim(config('publishing.staging_path'), '/') . "/{$this->rollbackTargetId}";
                if (!$target || $target->site_id !== $site->id || !is_dir($targetBuild)) {
                    throw new NonRetryableBuildException('Rollback target build no longer exists (pruned); cannot roll back to this deployment.');
                }
                $this->updateStatus('deploying', 'Rolling back...');
                if (!app(DeploymentGate::class)->mayGoLive($this->deployment)) {
                    throw new NonRetryableBuildException('Deployment superseded or reaped before the live swap; nothing was changed.');
                }
                $deployService->deploy($this->deployment, $targetBuild);
                $this->deployment->update([
                    'status' => 'rolled_back',
                    'artifact_path' => $targetBuild,
                    'completed_at' => now(),
                    'metadata' => array_merge($this->deployment->metadata ?? [], [
                        'current_step' => 'rolled_back',
                        'rolled_back_to' => $this->rollbackTargetId,
                    ]),
                ]);
                $this->broadcast('Rolled back successfully!');
                return;
            }

            $this->updateStatus('building', 'Starting build...');
            File::ensureDirectoryExists($stagingPath);

            // Assets publish INTO THE STAGING TREE so they ship with the build.
            // Writing them straight to the docroot (the old behavior) lost them
            // on every deploy: copyDeploy prunes files absent from staging, and
            // the symlink strategy swaps the docroot away entirely.
            AssetPublisher::reset();
            \App\Domain\Publishing\Services\WebpPictureEnricher::reset();
            AssetPublisher::setDeployTarget($stagingPath);

            // Compile theme CSS artifacts (before page rendering)
            $this->compileThemeArtifacts($site, $stagingPath);

            // Copy custom fonts to staging
            $this->copyCustomFonts($site, $stagingPath);

            // Verbatim design files (exact-copy imports) ship with the build.
            $siteFiles = \App\Domain\Publishing\Services\SiteFilesPublisher::publish($site, $stagingPath);
            if ($siteFiles > 0) {
                $this->broadcast("Copied {$siteFiles} design file(s)");
            }

            // Get publishable content
            $pages = $site->pages()->where('status', 'published')->orderBy('sort_order')->get();
            // Stream posts in id-ordered batches (lazyById) so a site with
            // thousands of posts never hydrates them all into the 128MB worker.
            // Build order is irrelevant — each post is its own static file.
            $postsQuery = $site->posts()->with('category')->where('status', 'published');
            $postCount = (clone $postsQuery)->count();

            $totalItems = $pages->count() + $postCount;
            $this->deployment->update(['metadata' => array_merge(
                $this->deployment->metadata ?? [],
                ['pages_total' => $totalItems, 'pages_built' => 0]
            )]);

            $built = 0;
            $validationResults = [];

            // Content Projection Layer: machine-readable sidecars. Gated behind
            // the per-site crawler policy (default off) so a site that has not
            // opted in publishes byte-for-byte as before.
            $projection = app(\App\Domain\Projection\ProjectionPublisher::class);
            $projectionOn = $projection->isEnabled($site);
            $projectionEntries = [];

            // PARALLEL FAN-OUT (opt-in; config publishing.parallel_posts). For a
            // large site, render the posts across worker processes instead of the
            // serial loop below, then re-enter this job to build pages + finalize
            // + deploy. The resumable posts loop makes that re-run skip everything
            // the chunks already rendered. Gated to projection-off sites (a
            // projection site must emit every sidecar in one pass) and skipped on
            // the re-run itself (metadata.parallel_rendered) so it doesn't loop.
            if ($this->shouldRenderPostsInParallel($postCount, $projectionOn)) {
                $this->dispatchParallelPostBuild($postsQuery, $totalItems);

                return; // finalize runs via the batch's completion callback
            }

            // Build pages
            foreach ($pages as $page) {
                $result = $buildService->buildAndValidate($page, $site->theme, $site);
                $html = \App\Domain\Publishing\Services\LocalePaths::localizeHtml($site, $page, $result['html']);
                $validationResults["page:{$page->slug}"] = $result['validation'];

                $pagePath = $this->getPagePath($page);
                self::writeAtomic("{$stagingPath}/{$pagePath}", $html);

                // Create version snapshot
                $version = $this->createVersion($page, 'page');

                if ($projectionOn) {
                    $proj = $projection->build($site, $page, $projection->urlForPath($pagePath), (string) $version->id);
                    $entry = $projection->writeSidecarFor($proj, $html, $stagingPath, $pagePath);
                    $this->recordProjection($entry, "page:{$page->slug}", $projectionEntries, $validationResults);
                    $this->storeProjectionSnapshot($version, $proj);
                }

                $built++;
                $this->updateProgress($built, $totalItems, "Building page: {$page->title}");
            }

            // Build posts
            foreach ($postsQuery->lazyById(300) as $post) {
                $postPath = $this->getPostPath($post);
                $dest = "{$stagingPath}/{$postPath}";

                // Resumable build: if the job timed out mid-build and was retried,
                // the staging dir already holds the posts rendered on the prior
                // pass. Skip them so the build CONVERGES across retries instead of
                // restarting from scratch (the root cause of the large-site stall).
                // Projection-on sites must re-emit every sidecar for the manifest,
                // so they rebuild fully each pass (projection is opt-in / rare).
                if (! $projectionOn && is_file($dest)) {
                    $built++;
                    $this->updateProgress($built, $totalItems, "Skipping already-built post: {$post->title}");
                    continue;
                }

                $result = $buildService->buildAndValidate($post, $site->theme, $site);
                $html = \App\Domain\Publishing\Services\LocalePaths::localizeHtml($site, $post, $result['html']);
                // Only retain non-clean validation — at thousands of posts, keeping
                // every passing result would itself pressure worker memory.
                if (empty($result['validation']['passed']) || !empty($result['validation']['warnings']) || !empty($result['validation']['errors'])) {
                    $validationResults["post:{$post->slug}"] = $result['validation'];
                }
                // Atomic (tmp + rename): a worker killed mid-write leaves no
                // half file, so "is_file() → already built" is a safe resume
                // criterion on the next attempt (F16).
                self::writeAtomic($dest, $html);

                $version = $this->createVersion($post, 'post');

                if ($projectionOn) {
                    $proj = $projection->build($site, $post, $projection->urlForPath($postPath), (string) $version->id);
                    $entry = $projection->writeSidecarFor($proj, $html, $stagingPath, $postPath);
                    $this->recordProjection($entry, "post:{$post->slug}", $projectionEntries, $validationResults);
                    $this->storeProjectionSnapshot($version, $proj);
                }

                $built++;
                $this->updateProgress($built, $totalItems, "Building post: {$post->title}");
            }

            // Static magazine viewers (W3-9: tenant domains are static-only —
            // published DTP issues become self-contained /magazine/... pages)
            $magBuilt = app(\App\Domain\Publishing\Services\MagazineStaticPublisher::class)
                ->publishForSite($site, $stagingPath);
            if ($magBuilt > 0) {
                $this->updateStatus('building', "Built {$magBuilt} magazine viewer(s)");
            }

            // Collections (Track G2): record detail pages + paginated
            // archives + static search indexes for static-tier collections —
            // independent of whether the site has posts.
            $publisher = app(\App\Domain\Collections\Services\CollectionPublishService::class);
            $collectionWarnings = array_merge(
                $publisher->buildAll($site, $stagingPath),
                $publisher->buildQueryFeeds($site, $stagingPath),
            );
            if ($collectionWarnings !== []) {
                $validationResults['site:collections'] = ['passed' => true, 'warnings' => $collectionWarnings, 'errors' => [], 'score_estimate' => 100];
            }
            \App\Models\Record::where('site_id', $site->id)->where('needs_republish', true)
                ->update(['needs_republish' => false, 'needs_republish_reason' => null]);
            $this->updateStatus('building', 'Built collections');

            // Generate blog index, archives, and RSS
            if ($postCount > 0) {
                // Blog index + category/tag/author archives (shared with delta
                // publish via ArchiveBuildService — §7 D1). Archive lint
                // warnings surface in the deploy log like page warnings.
                $archiveWarnings = app(\App\Domain\Publishing\Services\ArchiveBuildService::class)->buildAll($site, $stagingPath);
                if ($archiveWarnings !== []) {
                    $validationResults['site:archives'] = ['passed' => true, 'warnings' => $archiveWarnings, 'errors' => [], 'score_estimate' => 100];
                }

                // RSS feed
                $rssGenerator = app(RssFeedGenerator::class);
                File::put("{$stagingPath}/feed.xml", $rssGenerator->generate($site));

                // Per-category feeds at /{category}/feed.xml (F4)
                $feedCategories = $site->categories()
                    ->whereHas('posts', fn ($q) => $q->where('status', 'published'))
                    ->get();
                $catBase = \App\Domain\Publishing\Services\LocalePaths::categoryBase($site);
                foreach ($feedCategories as $feedCategory) {
                    File::ensureDirectoryExists("{$stagingPath}/{$catBase}{$feedCategory->slug}");
                    File::put(
                        "{$stagingPath}/{$catBase}{$feedCategory->slug}/feed.xml",
                        $rssGenerator->generateForCategory($site, $feedCategory)
                    );
                }
            }

            // Build homepage based on homepage_type setting
            $this->buildHomepage($site, $stagingPath);

            // Generate sitemap, robots.txt, llms.txt, 404 page, and redirects
            File::put("{$stagingPath}/sitemap.xml", $sitemapGenerator->generate($site));
            File::put("{$stagingPath}/robots.txt", $robotsGenerator->generate($site));
            File::put("{$stagingPath}/favicon.svg", app(\App\Domain\Publishing\Services\FaviconGenerator::class)->generate($site));
            if ($llmsTxt = app(\App\Domain\Publishing\Services\LlmsTxtGenerator::class)->generate($site)) {
                File::put("{$stagingPath}/llms.txt", $llmsTxt);
            }
            $this->build404Page($site, $stagingPath);
            $this->buildRedirectsManifest($site, $stagingPath);

            // Clean up static files for unpublished/draft posts
            $this->cleanUnpublishedPosts($site, $stagingPath);

            // Site-level projection manifest (once all sidecars are written).
            if ($projectionOn) {
                $projection->writeManifest($site, $projectionEntries, $stagingPath);
            }

            // F5 SEO lint — cross-page broken internal link check (warning-only)
            try {
                $linkWarnings = app(\App\Domain\Publishing\Services\InternalLinkChecker::class)->check(
                    $stagingPath,
                    50,
                    $site->custom_domain ? null : '/' . trim($site->deploySlug(), '/'),
                );
                if ($linkWarnings !== []) {
                    $validationResults['site:internal-links'] = [
                        'passed' => true,
                        'warnings' => $linkWarnings,
                        'errors' => [],
                        'score_estimate' => 100,
                    ];
                }
            } catch (\Throwable $e) {
                logger()->warning("Internal link check failed for site {$site->id}: {$e->getMessage()}");
            }

            // F27: hard integrity errors block the release — before the swap.
            $hardErrors = collect($validationResults)
                ->filter(fn ($v) => !empty($v['errors']))
                ->map(fn ($v, $k) => $k . ': ' . implode('; ', $v['errors']))
                ->merge((array) ($this->deployment->fresh()->metadata['hard_errors'] ?? []))
                ->values();
            if ($hardErrors->isNotEmpty()) {
                throw new NonRetryableBuildException("Build produced invalid output — not deployed:\n" . $hardErrors->implode("\n"));
            }

            // F15 fence: a reaped/superseded worker must not swap the live site.
            if (!app(DeploymentGate::class)->mayGoLive($this->deployment)) {
                throw new NonRetryableBuildException('Deployment superseded or reaped before the live swap; nothing was changed.');
            }

            // Deploy
            $this->updateStatus('deploying', 'Deploying files...');
            $deployService->deploy($this->deployment, $stagingPath);

            // Post-deploy layout audit (regression gate): sample pages at
            // phone + desktop widths — overflow, squeezed grids, background
            // seams, non-full-width dividers, edge-flush text. Fixes verified
            // only against one complaint kept silently breaking earlier fixes;
            // this surfaces any layout regression on the deploy that ships it.
            try {
                $layoutWarnings = $this->runLayoutAudit($site);
                if ($layoutWarnings !== []) {
                    $validationResults['site:layout-audit'] = [
                        'passed' => true,
                        'warnings' => $layoutWarnings,
                        'errors' => [],
                        'score_estimate' => 90,
                    ];
                }
            } catch (\Throwable $e) {
                logger()->warning("Layout audit failed for site {$site->id}: {$e->getMessage()}");
            }

            // Mark live with validation results
            $allPassed = collect($validationResults)->every(fn($v) => $v['passed']);
            $totalWarnings = collect($validationResults)->sum(fn($v) => count($v['warnings']));

            $this->deployment->update([
                'status' => 'live',
                'completed_at' => now(),
                'metadata' => array_merge($this->deployment->metadata ?? [], [
                    'current_step' => 'live',
                    'pages_built' => $totalItems,
                    // Kept under the historical key for the admin UI; these are
                    // heuristic output checks (F27), NOT Lighthouse measurements.
                    'lighthouse_checks' => [
                        'kind' => 'heuristic',
                        'all_passed' => $allPassed,
                        'total_warnings' => $totalWarnings,
                        'results' => $validationResults,
                    ],
                ]),
            ]);

            $this->broadcast('Published successfully!');

            // Purge the CDN edge so the new build is visible immediately.
            // No-op unless Cloudflare credentials are configured; never fatal.
            try {
                $purged = \App\Domain\Publishing\Services\CloudflarePurger::purgeSite(
                    $site,
                    (string) ($this->deployment->refresh()->artifact_path ?: $stagingPath)
                );
                if ($purged > 0) {
                    $this->broadcast('Cloudflare edge cache purged');
                }
            } catch (\Throwable $e) {
                logger()->warning("Cloudflare purge failed for site {$site->id}: {$e->getMessage()}");
            }

            // A successful FULL rebuild covers every page that existed when it
            // STARTED — flags raised since then survive (F17) …
            try {
                app(\App\Domain\References\Services\StalenessResolver::class)->clearForSite($site, $this->deployment->started_at);
            } catch (\Throwable $e) {
                logger()->warning("Staleness clear failed for site {$site->id}: {$e->getMessage()}");
            }

            // Clean old builds (state-aware retention)
            $this->cleanOldBuilds();

            // … and are republished in one coalesced follow-up batch.
            try {
                app(AutoPublishService::class)->followUp($site, $this->deployment->fresh());
            } catch (\Throwable $e) {
                logger()->warning("Auto-publish follow-up failed for site {$site->id}: {$e->getMessage()}");
            }
        } catch (\Throwable $e) {
            $this->handleFailure($e);
        } finally {
            // Static state must not outlive the job: a long-lived worker would
            // otherwise write later assets/fonts into THIS (old) build dir.
            AssetPublisher::reset();
        }
    }

    /**
     * F16 retry policy. A NonRetryableBuildException (hard output errors,
     * superseded deployment, missing rollback target) is terminal at once.
     * Anything else is retryable while attempts remain: the deployment stays
     * `building` (resumable staging dir), the error is recorded, and the
     * queue re-runs the job after the backoff. The LAST attempt — or a sync
     * run, which has no retries — ends in `failed`.
     */
    protected function handleFailure(\Throwable $e): void
    {
        $retryable = !($e instanceof NonRetryableBuildException)
            && $this->job !== null
            && $this->attempts() < $this->tries;

        if ($retryable) {
            $this->deployment->update([
                'status' => 'building',
                'error_log' => "Attempt {$this->attempts()} failed (will retry): {$e->getMessage()}",
                'metadata' => array_merge($this->deployment->metadata ?? [], [
                    'current_step' => 'building',
                    'retry_attempt' => $this->attempts(),
                    'last_error' => $e->getMessage(),
                    'heartbeat_at' => now()->toIso8601String(),
                ]),
            ]);
            $this->broadcast("Build attempt {$this->attempts()} failed — retrying: {$e->getMessage()}");
            throw $e; // let the queue schedule the next attempt
        }

        $this->markFailed($e);
        if ($e instanceof NonRetryableBuildException && $this->job !== null) {
            $this->fail($e); // terminal now — no further attempts

            return;
        }
        throw $e;
    }

    /** Queue hook: max attempts exceeded / timeout on the last attempt. */
    public function failed(\Throwable $e): void
    {
        try {
            $this->restoreModels();
            $this->deployment = Deployment::find($this->deploymentId) ?? $this->deployment;
            if ($this->deployment && !in_array($this->deployment->status, ['live', 'failed', 'rolled_back'], true)) {
                $this->markFailed($e);
            }
        } catch (\Throwable) {
            // nothing more to do
        }
    }

    private function markFailed(\Throwable $e): void
    {
        if (in_array($this->deployment->status, ['live', 'rolled_back'], true)) {
            return;
        }
        $this->deployment->update([
            'status' => 'failed',
            'error_log' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            'completed_at' => now(),
            'metadata' => array_merge($this->deployment->metadata ?? [], ['current_step' => 'failed']),
        ]);
        $this->broadcast("Build failed: {$e->getMessage()}");
    }

    /** Write a file atomically (tmp + rename on the same filesystem). */
    public static function writeAtomic(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        $tmp = $path . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(3));
        File::put($tmp, $contents);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Could not write {$path}");
        }
    }

    /**
     * Whether to fan the posts render out across workers instead of the serial
     * loop. Opt-in; async only; projection-off only (a projection site must emit
     * every sidecar in one pass); above the chunk-size threshold; and never on
     * the finalize re-run (metadata.parallel_rendered) so it can't loop.
     */
    protected function shouldRenderPostsInParallel(int $postCount, bool $projectionOn): bool
    {
        return (bool) config('publishing.parallel_posts')
            && config('queue.default') !== 'sync'
            && ! $projectionOn
            && $postCount > (int) config('publishing.parallel_chunk_size')
            && ! ($this->deployment->metadata['parallel_rendered'] ?? false);
    }

    /**
     * Fan post rendering out across worker processes. Each chunk builds its
     * posts into the shared staging dir; when ALL chunks succeed the batch
     * callback re-dispatches this job (same deployment) to build pages +
     * finalize + deploy — the resumable posts loop skips the rendered posts.
     * A chunk failure fails the deployment (the partial staging dir is never
     * deployed). Runs in the initial job's worker, so RLS context is already set.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $postsQuery
     */
    protected function dispatchParallelPostBuild($postsQuery, int $totalItems): void
    {
        $ids = (clone $postsQuery)->orderBy('id')->pluck('id')->all();
        $chunks = array_chunk($ids, (int) config('publishing.parallel_chunk_size'));

        // Mark rendered BEFORE dispatch so the finalize re-run skips this branch.
        $this->deployment->update(['metadata' => array_merge($this->deployment->metadata ?? [], [
            'parallel_rendered' => true,
            'parallel_chunks' => count($chunks),
            'current_step' => 'building',
        ])]);

        $jobs = [];
        foreach ($chunks as $i => $chunkIds) {
            $jobs[] = new BuildPostsChunkJob($this->deploymentId, $this->tenantId, array_values($chunkIds), $i);
        }

        // Only scalars in the callbacks (they serialize onto the queue); restore
        // RLS context inside them before touching tenant-scoped rows.
        $deploymentId = $this->deploymentId;
        $tenantId = $this->tenantId;
        $type = $this->type;

        Bus::batch($jobs)
            ->name("publish-posts-{$this->deploymentId}")
            ->onConnection('builds')
            ->onQueue(config('queue.connections.builds.queue', 'builds'))
            ->allowFailures(false)
            ->then(function (Batch $batch) use ($deploymentId, $tenantId, $type) {
                $tid = preg_replace('/[^a-f0-9\-]/', '', $tenantId);
                DB::unprepared("SET app.current_tenant_id = '{$tid}'");
                $deployment = Deployment::find($deploymentId);
                if ($deployment && ! in_array($deployment->status, ['live', 'failed', 'rolled_back', 'cancelled'], true)) {
                    PublishSiteJob::dispatch($deployment, $type);
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($deploymentId, $tenantId) {
                $tid = preg_replace('/[^a-f0-9\-]/', '', $tenantId);
                DB::unprepared("SET app.current_tenant_id = '{$tid}'");
                $deployment = Deployment::find($deploymentId);
                if ($deployment && ! in_array($deployment->status, ['live', 'failed', 'rolled_back', 'cancelled'], true)) {
                    $deployment->update([
                        'status' => 'failed',
                        'error_log' => 'Parallel post render failed: ' . $e->getMessage(),
                        'completed_at' => now(),
                    ]);
                }
            })
            ->dispatch();

        $this->updateStatus('building', "Rendering {$totalItems} items across " . count($chunks) . ' parallel chunks');
    }

    private function createVersion($content, string $type): PageVersion
    {
        $blocks = $content->blocks()->orderBy('order')->get()->toArray();
        $lastVersion = PageVersion::where("{$type}_id", $content->id)
            ->orderByDesc('version_number')
            ->first();

        return PageVersion::create([
            "{$type}_id" => $content->id,
            'blocks_snapshot' => $blocks,
            'seo_snapshot' => $content->seo_meta ?? [],
            'published_by' => $this->deployment->triggered_by,
            'published_at' => now(),
            'version_number' => ($lastVersion?->version_number ?? 0) + 1,
        ]);
    }

    /**
     * Phase 4.5 — persist the full internal projection alongside its version
     * for the internal consumers (Sumi / Ledger / Export). Populated only when
     * the site has opted into the projection, to avoid storage bloat.
     */
    private function storeProjectionSnapshot(PageVersion $version, \App\Domain\Projection\Projection $proj): void
    {
        $arr = $proj->toArray();
        $version->update([
            'projection_snapshot' => $arr,
            'projection_hash' => $arr['source']['content_hash'],
        ]);
    }

    /**
     * Fold a projection publish result into the deploy state: a parity mismatch
     * surfaces as a build error (and no sidecar shipped); success adds a
     * manifest entry.
     *
     * @param array<string,mixed>       $entry
     * @param list<array<string,mixed>> $entries
     * @param array<string,mixed>       $validationResults
     */
    private function recordProjection(array $entry, string $key, array &$entries, array &$validationResults): void
    {
        if (! empty($entry['__parity_failed'])) {
            $errors = array_map(
                fn ($m) => 'Projection parity mismatch — ' . ($m['key'] ?? '?') . ': "' . ($m['text'] ?? '') . '" not found in rendered HTML',
                $entry['missing'] ?? []
            );
            $validationResults["projection:{$key}"] = [
                'passed' => false,
                'warnings' => [],
                'errors' => $errors,
                'score_estimate' => 0,
            ];

            return;
        }

        $entries[] = $entry;
    }

    private function getPagePath($page): string
    {
        // identical to the old inline logic for default-locale content;
        // translated content publishes under /{locale}/ with the suffix stripped
        return \App\Domain\Publishing\Services\LocalePaths::pagePath($this->deployment->site, $page);
    }

    private function getPostPath($post): string
    {
        return \App\Domain\Publishing\Services\LocalePaths::postPath($this->deployment->site, $post);
    }

    private function updateStatus(string $status, string $message): void
    {
        $this->deployment->update([
            'status' => $status,
            'started_at' => $this->deployment->started_at ?? now(),
            'metadata' => array_merge($this->deployment->metadata ?? [], ['current_step' => $status, 'heartbeat_at' => now()->toIso8601String()]),
        ]);
        $this->broadcast($message);
    }

    private function updateProgress(int $built, int $total, string $message): void
    {
        $this->deployment->update([
            'metadata' => array_merge($this->deployment->metadata ?? [], [
                'pages_built' => $built,
                'pages_total' => $total,
                'heartbeat_at' => now()->toIso8601String(),
            ]),
        ]);
        $this->broadcast($message);
    }

    private function broadcast(string $message): void
    {
        try {
            event(new DeploymentProgressEvent(
                $this->deployment->site_id,
                $this->deployment->id,
                $this->deployment->status,
                $message,
                $this->deployment->metadata ?? [],
            ));
        } catch (\Throwable) {
            // Broadcasting may be disabled
        }
    }

    /**
     * Common template variables for all archive pages (nav, design tokens, CSS).
     */
    private function build404Page($site, string $stagingPath): void
    {
        $themeConfig = $site->theme?->config ?? [];
        $menuRenderer = app(MenuRenderer::class);

        $html = View::make('publishing.error-404', [
            'site' => $site,
            'lang' => $themeConfig['lang'] ?? 'en',
            'criticalCss' => $themeConfig['critical_css'] ?? '',
            'customCss' => $site->settings['custom_css'] ?? '',
            'navigation' => $menuRenderer->renderByLocation($site, 'header'),
            'footerNavigation' => $menuRenderer->renderByLocation($site, 'footer'),
        ])->render();

        File::put("{$stagingPath}/404.html", $html);
    }

    private function buildRedirectsManifest($site, string $stagingPath): void
    {
        $redirects = \App\Models\Redirect::where('site_id', $site->id)->get();

        // Security headers for the published static site (FIX-A4b): the CMS
        // previously emitted no CSP/HSTS/X-Frame/etc on published output, so
        // XSS had no backstop. Written on EVERY publish, redirects or not.
        $htaccess = "# CMS security headers\n";
        $htaccess .= "<IfModule mod_headers.c>\n";
        $htaccess .= "  Header always set X-Content-Type-Options \"nosniff\"\n";
        $htaccess .= "  Header always set X-Frame-Options \"SAMEORIGIN\"\n";
        $htaccess .= "  Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n";
        $htaccess .= "  Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"\n";
        $htaccess .= "  Header always set Content-Security-Policy \"default-src 'self'; img-src 'self' data: https:; media-src 'self' https:; font-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com; connect-src 'self' https:\"\n";
        $htaccess .= "</IfModule>\n";

        if (!$redirects->isEmpty()) {
            // HTML redirect stubs — the only mechanism that works on every
            // static host (nginx ignores .htaccess; _redirects is
            // Netlify/CF-Pages-only). Written for simple path sources; regex
            // sources beyond a trailing '/?' can't map to a single file.
            // Containment at WRITE time (F04): the stub path must resolve
            // inside the staging tree, the target must be a safe destination,
            // and a stub never replaces a real page the build produced.
            foreach ($redirects as $r) {
                if (!RedirectRules::isSafeTarget((string) $r->target_url)) {
                    Log::warning("Redirect {$r->id}: unsafe target skipped");
                    continue;
                }
                $stubPath = RedirectRules::stubPath($stagingPath, (string) $r->source_path);
                if ($stubPath === null) {
                    if (!$r->is_regex) {
                        Log::warning("Redirect {$r->id}: source '{$r->source_path}' cannot map to a stub inside the build — skipped");
                    }
                    continue;
                }
                if (is_file($stubPath)) {
                    Log::warning("Redirect {$r->id}: '{$r->source_path}' collides with a published page — page kept, stub skipped");
                    continue;
                }
                $target = e($r->target_url);
                $stub = '<!doctype html><html><head><meta charset="utf-8">'
                    . '<meta http-equiv="refresh" content="0;url=' . $target . '">'
                    . '<link rel="canonical" href="' . $target . '">'
                    . '<meta name="robots" content="noindex">'
                    . '<title>Redirecting…</title></head>'
                    . '<body><a href="' . $target . '">Redirecting…</a>'
                    . '<script>location.replace(' . json_encode($r->target_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . ');</script>'
                    . '</body></html>';
                File::ensureDirectoryExists(dirname($stubPath));
                File::put($stubPath, $stub);
            }

            // Line-based formats: one row per line, so a row that contains
            // whitespace/control characters or an unsafe target is dropped
            // rather than allowed to forge extra rules.
            $safe = $redirects->filter(fn ($r) => RedirectRules::isSafeForLineOutput(
                (string) $r->source_path, (string) $r->target_url, (bool) $r->is_regex
            ));

            // _redirects file (Netlify/Cloudflare Pages format)
            $lines = [];
            foreach ($safe as $r) {
                $lines[] = "{$r->source_path} {$r->target_url} {$r->status_code}";
            }
            File::put("{$stagingPath}/_redirects", implode("\n", $lines));

            // .htaccess RewriteRules for Apache
            $htaccess .= "\n# CMS Redirects\nRewriteEngine On\n";
            foreach ($safe as $r) {
                $flag = (int) $r->status_code === 301 ? 'R=301,L' : 'R=302,L';
                $pattern = $r->is_regex ? ltrim($r->source_path, '/') : preg_quote(ltrim($r->source_path, '/'), '~');
                $htaccess .= "RewriteRule ^" . $pattern . "/?$ {$r->target_url} [{$flag}]\n";
            }
        }

        // Append to existing .htaccess or create new
        $htaccessPath = "{$stagingPath}/.htaccess";
        if (file_exists($htaccessPath)) {
            File::append($htaccessPath, "\n" . $htaccess);
        } else {
            File::put($htaccessPath, $htaccess);
        }
    }

    private function buildHomepage($site, string $stagingPath): void
    {
        $settings = $site->settings ?? [];
        $homepageType = $settings['homepage_type'] ?? 'page';

        if ($homepageType === 'grid') {
            $gridId = $settings['homepage_grid_id'] ?? null;
            if (!$gridId) return;

            $grid = Grid::with('positions')->find($gridId);
            if (!$grid) return;

            // Create a virtual page for the grid renderer
            $virtualPage = new Page();
            $virtualPage->id = '00000000-0000-0000-0000-000000000000';
            $virtualPage->site_id = $site->id;
            $virtualPage->title = $site->name;
            $virtualPage->slug = '';
            $virtualPage->status = 'published';

            $gridRenderer = app(GridRenderer::class);
            $gridResult = $gridRenderer->render($grid, $virtualPage, $site);

            $tokenGenerator = app(DesignTokenGenerator::class);
            $themeConfig = $site->theme?->config ?? [];
            $rssUrl = $site->publicBaseUrl() . '/feed.xml';

            $html = View::make('publishing.grid-layout', [
                'headContent' => '<title>' . e($site->name) . '</title>',
                'headScripts' => $settings['head_scripts'] ?? '',
                'bodyScripts' => $settings['body_scripts'] ?? '',
                'customCss' => $settings['custom_css'] ?? '',
                'criticalCss' => $themeConfig['critical_css'] ?? '',
                'fontPreloads' => '',
                'cssFile' => $themeConfig['css_file'] ?? null,
                'gridCss' => $gridResult['css'],
                'gridHtml' => $gridResult['html'],
                'designTokensCss' => $tokenGenerator->generate($site),
                'hookHeadScripts' => '',
                'hookBodyOpen' => '',
                'hookBodyClose' => '',
                'site' => $site,
                'rssUrl' => $rssUrl,
                'lang' => $themeConfig['lang'] ?? 'bg',
            ])->render();

            File::put("{$stagingPath}/index.html", $html);
        } elseif ($homepageType === 'blog') {
            // Blog feed as homepage — copy blog index to root
            if (file_exists("{$stagingPath}/blog/index.html")) {
                File::copy("{$stagingPath}/blog/index.html", "{$stagingPath}/index.html");
            }
        }
        // For 'page' type, getPagePath() already handles writing index.html
    }

    /**
     * Remove static files for posts that are no longer published (draft, archived, deleted).
     * This ensures unpublished posts don't remain accessible on the public site.
     */
    /**
     * Remove static files for posts no longer published + clean old /blog/ paths.
     */
    private function cleanUnpublishedPosts($site, string $stagingPath): void
    {
        $publicPath = config('publishing.public_path');

        // Build set of all valid published post paths
        $publishedPaths = [];
        $publishedSlugs = [];
        foreach ($site->posts()->with('category')->where('status', 'published')->lazyById(300) as $post) {
            $publishedPaths[] = $this->getPostPath($post);
            $publishedSlugs[] = $post->slug;
        }

        // Also get all page slugs so we don't accidentally delete pages
        $pageSlugs = $site->pages()->pluck('slug')->toArray();

        // Clean old /blog/ post directories (legacy paths from before URL change)
        $blogPath = $publicPath . '/blog';
        if (is_dir($blogPath)) {
            foreach (scandir($blogPath) as $entry) {
                if ($entry === '.' || $entry === '..' || !is_dir($blogPath . '/' . $entry)) continue;
                // Skip known blog infrastructure dirs
                if (in_array($entry, ['category', 'tag', 'author', 'page'])) continue;

                $fullPath = $blogPath . '/' . $entry;
                // If it has index.html, it's a post at /blog/{slug}/ — remove it
                if (file_exists($fullPath . '/index.html')) {
                    File::deleteDirectory($fullPath);
                } else {
                    // Category subfolder — clean post dirs inside
                    foreach (scandir($fullPath) as $sub) {
                        if ($sub === '.' || $sub === '..') continue;
                        $subPath = $fullPath . '/' . $sub;
                        if (is_dir($subPath) && file_exists($subPath . '/index.html')) {
                            File::deleteDirectory($subPath);
                        }
                    }
                    // Remove category dir if empty
                    if (is_dir($fullPath) && count(scandir($fullPath)) === 2) rmdir($fullPath);
                }
            }
        }

        // Clean category dirs at root level for unpublished posts
        $categories = $site->categories()->get();
        foreach ($categories as $category) {
            $catPath = $publicPath . '/' . $category->slug;
            if (!is_dir($catPath)) continue;

            foreach (scandir($catPath) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $postDir = $catPath . '/' . $entry;
                if (!is_dir($postDir) || !file_exists($postDir . '/index.html')) continue;

                $expectedPath = $category->slug . '/' . $entry . '/index.html';
                if (!in_array($expectedPath, $publishedPaths)) {
                    File::deleteDirectory($postDir);
                }
            }
            // Remove category dir if empty
            if (is_dir($catPath) && count(scandir($catPath)) === 2) rmdir($catPath);
        }
    }

    /**
     * Compile theme CSS artifacts for each mode the site supports.
     */
    private function compileThemeArtifacts($site, string $stagingPath): void
    {
        try {
            $compiler = app(\App\Services\Theme\ThemeCompiler::class);
            $modes = ['light']; // Always compile light mode

            // Check if theme has dark mode
            if ($site->theme?->modes && in_array('dark', $site->theme->modes)) {
                $modes[] = 'dark';
            }

            foreach ($modes as $mode) {
                $version = $compiler->compile($site->id, $mode);
                if ($version && $version->css_artifact_path) {
                    // Copy CSS artifact to staging path
                    $cssContent = \Illuminate\Support\Facades\Storage::disk('local')->get($version->css_artifact_path);
                    if ($cssContent) {
                        File::ensureDirectoryExists("{$stagingPath}/themes/site-{$site->id}");
                        File::put("{$stagingPath}/{$version->css_artifact_path}", $cssContent);
                    }
                    $this->broadcast("Compiled theme ({$mode} mode)");
                }
            }
        } catch (\Throwable $e) {
            // Theme compilation failure should not block the publish
            $this->broadcast("Theme compilation skipped: {$e->getMessage()}");
        }
    }

    private function cleanOldBuilds(): void
    {
        // Live-safe pruning: never delete a build a live site symlink targets
        // (FIX-B6a). Keeps enough per-tenant history for a rollback window.
        \App\Domain\Publishing\Services\BuildRetention::prune(10);
    }

    /**
     * Copy custom font files to the staging directory.
     */
    private function copyCustomFonts(Site $site, string $stagingPath): void
    {
        $fonts = $site->settings['custom_fonts'] ?? [];
        if (empty($fonts)) return;

        $fontsDir = $stagingPath . '/fonts';
        File::ensureDirectoryExists($fontsDir);

        $disk = \Illuminate\Support\Facades\Storage::disk('assets');
        foreach ($fonts as $font) {
            $path = $font['path'] ?? '';
            $filename = $font['filename'] ?? '';
            if (!$path || !$filename || !$disk->exists($path)) continue;

            $dest = $fontsDir . '/' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $filename);
            file_put_contents($dest, $disk->get($path));
        }
    }

    /**
     * Audit a sample of just-deployed pages for layout regressions at phone
     * and desktop widths (scripts/mobile-audit.mjs). Returns human-readable
     * warnings; empty when clean or when the audit tooling is unavailable.
     *
     * @return string[]
     */
    private function runLayoutAudit(Site $site): array
    {
        $script = base_path('scripts/mobile-audit.mjs');
        if (!file_exists($script)) {
            return [];
        }

        $base = $site->custom_domain
            ? "https://{$site->custom_domain}"
            : 'https://ensodo.eu/' . trim($site->deploySlug(), '/');

        // homepage + a recent page + a recent post — cheap but representative
        $urls = [$base . '/'];
        $page = Page::where('site_id', $site->id)->where('status', 'published')
            ->whereRaw("slug != ''")->orderByDesc('updated_at')->first();
        if ($page) {
            $urls[] = rtrim($base . \App\Domain\Publishing\Services\LocalePaths::urlPath($site, $page), '/') . '/';
        }
        $post = \App\Models\Post::where('site_id', $site->id)->where('status', 'published')
            ->orderByDesc('updated_at')->first();
        if ($post) {
            $urls[] = rtrim($base . \App\Domain\Publishing\Services\LocalePaths::urlPath($site, $post), '/') . '/';
        }

        $warnings = [];
        foreach (array_unique($urls) as $url) {
            foreach (['390x844' => '📱', '1280x900' => '🖥'] as $size => $icon) {
                $out = shell_exec('node ' . escapeshellarg($script) . ' ' . escapeshellarg($url) . ' ' . $size . ' 2>/dev/null');
                $r = json_decode((string) $out, true);
                if (!is_array($r) || !empty($r['error'])) {
                    continue;
                }
                $label = parse_url($url, PHP_URL_PATH) ?: '/';
                if (!empty($r['horizontalOverflow'])) {
                    $warnings[] = "{$icon} {$label}: horizontal overflow ({$r['scrollWidth']}px on {$r['viewport']}px)";
                }
                foreach ($r['squeezedGrids'] ?? [] as $g) {
                    $warnings[] = "{$icon} {$label}: squeezed grid {$g['el']} ({$g['columns']}×{$g['colWidth']}px)";
                }
                foreach ($r['sectionSeams'] ?? [] as $g) {
                    $warnings[] = "{$icon} {$label}: background seam {$g['gap']}px below content in {$g['el']}";
                }
                foreach ($r['narrowBanners'] ?? [] as $g) {
                    $warnings[] = "{$icon} {$label}: divider image {$g['width']}px on {$g['viewport']}px viewport";
                }
                if (($r['edgeFlushTextBlocks'] ?? 0) > 3) {
                    $warnings[] = "{$icon} {$label}: {$r['edgeFlushTextBlocks']} text blocks flush against the screen edge";
                }
            }
        }

        return $warnings;
    }
}
