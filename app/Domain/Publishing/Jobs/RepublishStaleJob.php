<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Builds ONLY the requested stale pages/posts into a staging directory and
 * parks the deployment at status 'staged'. Nothing touches the live docroot —
 * promotion is a separate, human-confirmed step (StaleContentController).
 *
 * Per-page failures are collected in metadata and do not abort the batch.
 *
 * Sitemap/robots/llms.txt/feeds regenerate with every non-empty batch
 * (FIX-B7a); when the batch contains POSTS, the blog index and
 * category/tag/author archives (+ per-category feeds) rebuild too (§7 D1) —
 * a post edit changes every archive that lists it.
 */
class RepublishStaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    public string $deploymentId;
    public string $tenantId;

    public function __construct(Deployment $deployment)
    {
        // Store IDs instead of models to avoid RLS issues during deserialization
        $this->deploymentId = $deployment->id;
        $this->tenantId = $deployment->site->tenant_id;
    }

    public function handle(BuildPageService $buildService): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $deployment = Deployment::findOrFail($this->deploymentId);
        $site = $deployment->site;
        $site->load('theme');
        $stagingPath = storage_path("app/builds/{$deployment->id}");

        try {
            $deployment->update([
                'status' => 'building',
                'started_at' => now(),
                'metadata' => array_merge($deployment->metadata ?? [], ['current_step' => 'building']),
            ]);
            File::ensureDirectoryExists($stagingPath);

            // Assets publish into the staging tree (same as full publish) —
            // deployPartial's per-file merge then carries them to the docroot.
            AssetPublisher::reset();
            AssetPublisher::setDeployTarget($stagingPath);

            $targets = $deployment->metadata['targets'] ?? ['pages' => [], 'posts' => []];
            $built = [];
            $failed = [];

            $pages = Page::whereIn('id', $targets['pages'] ?? [])
                ->where('site_id', $site->id)
                ->where('status', 'published')
                ->get();
            foreach ($pages as $page) {
                try {
                    $html = \App\Domain\Publishing\Services\LocalePaths::localizeHtml($site, $page, $buildService->buildAndValidate($page, $site->theme, $site)['html']);
                    $path = $this->getPagePath($site, $page);
                    File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
                    File::put("{$stagingPath}/{$path}", $html);
                    $built[] = ['type' => 'page', 'id' => $page->id, 'title' => $page->title, 'path' => $path, 'stamp' => optional($page->updated_at)->toIso8601String()];
                } catch (\Throwable $e) {
                    $failed[] = ['type' => 'page', 'id' => $page->id, 'title' => $page->title, 'error' => $e->getMessage()];
                }
            }

            $posts = Post::with('category')
                ->whereIn('id', $targets['posts'] ?? [])
                ->where('site_id', $site->id)
                ->where('status', 'published')
                ->get();
            foreach ($posts as $post) {
                try {
                    $html = \App\Domain\Publishing\Services\LocalePaths::localizeHtml($site, $post, $buildService->buildAndValidate($post, $site->theme, $site)['html']);
                    $path = $this->getPostPath($site, $post);
                    File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
                    File::put("{$stagingPath}/{$path}", $html);
                    $built[] = ['type' => 'post', 'id' => $post->id, 'title' => $post->title, 'path' => $path, 'stamp' => optional($post->updated_at)->toIso8601String()];
                } catch (\Throwable $e) {
                    $failed[] = ['type' => 'post', 'id' => $post->id, 'title' => $post->title, 'error' => $e->getMessage()];
                }
            }

            // Collections (Track G2): stale records rebuild their own detail
            // page; every touched static-tier collection then gets its archive
            // pages + search index regenerated (a record change alters both).
            $staleRecords = \App\Models\Record::with('collection')
                ->whereIn('id', $targets['records'] ?? [])
                ->where('site_id', $site->id)
                ->get();
            if ($staleRecords->isNotEmpty()) {
                $collectionPublisher = app(\App\Domain\Collections\Services\CollectionPublishService::class);
                $touchedCollections = [];
                foreach ($staleRecords as $record) {
                    try {
                        $collection = $record->collection;
                        $buildsPages = $collection
                            && ($collection->tier !== 'dynamic' || ($collection->settings['static_details'] ?? true));
                        if ($buildsPages && $record->status === 'published') {
                            $collectionPublisher->buildRecordPage($site, $collection, $record, $stagingPath);
                        }
                        // Unpublished/draft records build no page; the archive +
                        // index rebuild below removes them from listings.
                        if ($collection) {
                            $touchedCollections[$collection->id] = $collection;
                        }
                        $built[] = ['type' => 'record', 'id' => $record->id, 'title' => $record->title, 'path' => null, 'stamp' => optional($record->updated_at)->toIso8601String()];
                    } catch (\Throwable $e) {
                        $failed[] = ['type' => 'record', 'id' => $record->id, 'title' => $record->title, 'error' => $e->getMessage()];
                    }
                }
                foreach ($touchedCollections as $collection) {
                    try {
                        $collectionPublisher->rebuildArchiveAndIndex($site, $collection, $stagingPath);
                    } catch (\Throwable $e) {
                        logger()->warning("Delta collection rebuild failed ({$collection->slug}, site {$site->id}): {$e->getMessage()}");
                    }
                }
            }

            // FIX-B7a: regenerate sitemap.xml / feed.xml / robots.txt so a delta
            // publish doesn't leave them pointing at old/dead URLs until the next
            // full publish. Cheap + deterministic; merged live by deployPartial.
            if ($built !== []) {
                try {
                    File::put("{$stagingPath}/sitemap.xml", app(\App\Domain\Publishing\Services\SitemapGenerator::class)->generate($site));
                    File::put("{$stagingPath}/robots.txt", app(\App\Domain\Publishing\Services\RobotsGenerator::class)->generate($site));
                    File::put("{$stagingPath}/favicon.svg", app(\App\Domain\Publishing\Services\FaviconGenerator::class)->generate($site));
                    if ($llmsTxt = app(\App\Domain\Publishing\Services\LlmsTxtGenerator::class)->generate($site)) {
                        File::put("{$stagingPath}/llms.txt", $llmsTxt);
                    }
                    if ($site->posts()->where('status', 'published')->exists()) {
                        File::put("{$stagingPath}/feed.xml", app(\App\Domain\Publishing\Services\RssFeedGenerator::class)->generate($site));
                    }
                } catch (\Throwable $e) {
                    logger()->warning("Delta sitemap/feed regeneration failed for site {$site->id}: {$e->getMessage()}");
                }
            }

            // §7 D1: a changed post alters every archive that lists it — rebuild
            // the blog index + category/tag/author archives and per-category
            // feeds in the same batch (pages don't affect archives; skipped).
            if (array_filter($built, fn ($b) => $b['type'] === 'post') !== []) {
                try {
                    $archiveWarnings = app(\App\Domain\Publishing\Services\ArchiveBuildService::class)->buildAll($site, $stagingPath);
                    foreach ($archiveWarnings as $w) {
                        logger()->warning("Archive lint ({$site->name}): {$w}");
                    }

                    $rssGenerator = app(\App\Domain\Publishing\Services\RssFeedGenerator::class);
                    $feedCategories = $site->categories()
                        ->whereHas('posts', fn ($q) => $q->where('status', 'published'))
                        ->get();
                    foreach ($feedCategories as $feedCategory) {
                        File::ensureDirectoryExists("{$stagingPath}/{$feedCategory->slug}");
                        File::put(
                            "{$stagingPath}/{$feedCategory->slug}/feed.xml",
                            $rssGenerator->generateForCategory($site, $feedCategory)
                        );
                    }
                } catch (\Throwable $e) {
                    logger()->warning("Delta archive rebuild failed for site {$site->id}: {$e->getMessage()}");
                }
            }

            $deployment->update([
                'status' => 'staged',
                'artifact_path' => $stagingPath,
                'metadata' => array_merge($deployment->metadata ?? [], [
                    'current_step' => 'staged',
                    'built' => $built,
                    'failed' => $failed,
                    'pages_built' => count($built),
                    'pages_total' => count($built) + count($failed),
                ]),
            ]);

            // Auto-republish (Phase 4.1b): the entity publish click was the
            // confirmation — promote immediately, log every page
            if (($deployment->metadata['auto_promote'] ?? false) === true && $built !== []) {
                $this->autoPromote($deployment, $site, $stagingPath, $built);
            }
        } catch (\Throwable $e) {
            $deployment->update([
                'status' => 'failed',
                'error_log' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * Promote a staged auto batch to live: per-file merge, clear flags only
     * for built sources, one visible activity-log entry per page.
     * A promote failure leaves the batch at 'staged' for the manual flow.
     */
    private function autoPromote(Deployment $deployment, $site, string $stagingPath, array $built): void
    {
        try {
            app(\App\Domain\Publishing\Services\DeployService::class)
                ->deployPartial($deployment, $stagingPath);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                "Auto-republish promote failed for site {$site->id}: {$e->getMessage()} — batch left staged for manual promotion."
            );

            return;
        }

        // Clear flags only for items not re-flagged since the build (§7 D2).
        app(\App\Domain\References\Services\StalenessResolver::class)->clearBuiltIfUnchanged($built);

        // Remove files left at OLD paths by slug renames (§7 — a delta merge
        // can't prune what it doesn't know about; the services recorded it).
        try {
            $removed = app(\App\Domain\Publishing\Services\StalePathCleaner::class)->removeFor($site, $built);
            if ($removed !== []) {
                logger()->info("Delta promote removed stale renamed files for {$site->name}: " . implode(', ', $removed));
            }
        } catch (\Throwable $e) {
            logger()->warning("Stale path cleanup failed for site {$site->id}: {$e->getMessage()}");
        }

        $log = app(\App\Services\ActivityLogService::class);
        $reason = $deployment->metadata['reason'] ?? 'stale content';
        foreach ($built as $item) {
            $log->log('page.auto_republished', $site->id, $item['type'], $item['id'], [
                'title' => $item['title'],
                'reason' => $reason,
                'deployment_id' => $deployment->id,
            ]);
        }

        $deployment->update([
            'status' => 'live',
            'completed_at' => now(),
            'metadata' => array_merge($deployment->fresh()->metadata ?? [], ['current_step' => 'live']),
        ]);
    }


    // Same path logic as PublishSiteJob (single source of truth: LocalePaths)
    private function getPagePath($site, Page $page): string
    {
        return \App\Domain\Publishing\Services\LocalePaths::pagePath($site, $page);
    }

    private function getPostPath($site, Post $post): string
    {
        return \App\Domain\Publishing\Services\LocalePaths::postPath($site, $post);
    }
}
