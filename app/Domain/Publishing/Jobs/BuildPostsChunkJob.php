<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\LocalePaths;
use App\Domain\Publishing\Services\WebpPictureEnricher;
use App\Models\Deployment;
use App\Models\PageVersion;
use App\Models\Post;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Renders one chunk of a site's posts into the shared staging directory as part
 * of a parallel full publish (config publishing.parallel_posts). Many of these
 * run concurrently — one per id-chunk — after which PublishSiteJob is re-entered
 * to build pages, collections, sitemap and DEPLOY. The re-run's resumable posts
 * loop skips everything rendered here, so this job only has to produce the post
 * HTML files + version snapshots.
 *
 * Self-contained (no shared state with PublishSiteJob) so it can run in its own
 * worker: RLS context is set from the tenant id, the post URL/localization use
 * the same LocalePaths helpers, and version creation mirrors
 * PublishSiteJob::createVersion.
 */
class BuildPostsChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 1800;

    /**
     * @param  list<string|int>  $postIds
     */
    public function __construct(
        public string $deploymentId,
        public string $tenantId,
        public array $postIds,
        public int $chunkIndex,
    ) {
        $this->onConnection('builds');
    }

    public function middleware(): array
    {
        // Unique per chunk so chunks of the SAME deployment run concurrently
        // (a shared deploymentId key would serialize them and defeat the point).
        return [(new WithoutOverlapping("{$this->deploymentId}-chunk-{$this->chunkIndex}"))
            ->dontRelease()
            ->expireAfter($this->timeout + 120)];
    }

    public function handle(BuildPageService $buildService): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $deployment = Deployment::find($this->deploymentId);
        if (! $deployment || in_array($deployment->status, ['live', 'failed', 'rolled_back', 'cancelled'], true)) {
            return; // deployment finished or was reaped — nothing to render
        }
        if ($this->batch()?->cancelled()) {
            return;
        }

        $site = $deployment->site;
        $site->load('theme');
        $stagingPath = rtrim(config('publishing.staging_path'), '/') . "/{$this->deploymentId}";

        // Assets referenced by these posts publish into the staging tree (each
        // worker has fresh static state; per-file writes are idempotent).
        AssetPublisher::reset();
        WebpPictureEnricher::reset();
        AssetPublisher::setDeployTarget($stagingPath);

        Post::with('category')
            ->whereIn('id', $this->postIds)
            ->where('status', 'published')
            ->lazyById(100)
            ->each(function (Post $post) use ($buildService, $site, $stagingPath, $deployment) {
                $path = LocalePaths::postPath($site, $post);
                $dest = "{$stagingPath}/{$path}";
                // Resumable across this chunk's own retries.
                if (is_file($dest)) {
                    return;
                }

                $result = $buildService->buildAndValidate($post, $site->theme, $site);
                if (!empty($result['validation']['errors'])) {
                    // F27: record the hard error for the finalize run; never
                    // write invalid output into the staging tree.
                    $fresh = Deployment::find($this->deploymentId);
                    $errors = (array) ($fresh?->metadata['hard_errors'] ?? []);
                    $errors[] = "post:{$post->slug}: " . implode('; ', $result['validation']['errors']);
                    $fresh?->update(['metadata' => array_merge($fresh->metadata ?? [], ['hard_errors' => $errors])]);

                    return;
                }
                $html = LocalePaths::localizeHtml($site, $post, $result['html']);
                PublishSiteJob::writeAtomic($dest, $html);

                $this->createVersion($post, $deployment->triggered_by);
                \App\Domain\Publishing\Services\DeploymentGate::heartbeat($deployment);
            });
    }

    /** Mirrors PublishSiteJob::createVersion for posts. */
    private function createVersion(Post $post, ?string $publishedBy): void
    {
        $blocks = $post->blocks()->orderBy('order')->get()->toArray();
        $last = PageVersion::where('post_id', $post->id)->orderByDesc('version_number')->first();

        PageVersion::create([
            'post_id' => $post->id,
            'blocks_snapshot' => $blocks,
            'seo_snapshot' => $post->seo_meta ?? [],
            'published_by' => $publishedBy,
            'published_at' => now(),
            'version_number' => ($last?->version_number ?? 0) + 1,
        ]);
    }
}
