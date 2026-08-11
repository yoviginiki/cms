<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\BuildPostsChunkJob;
use App\Domain\Publishing\Jobs\PublishSiteJob;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\LocalePaths;
use App\Models\Category;
use App\Models\Deployment;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Parallel full-publish fan-out (config publishing.parallel_posts). The gate is
 * OFF by default; these cover the decision rules, that dispatch fans one chunk
 * job per id-chunk, and that a chunk job renders its posts into staging.
 */
class ParallelPostBuildTest extends TestCase
{
    private function makeSite(): Site
    {
        $this->setTenantScope($this->owner);

        return Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function deployment(Site $site, array $metadata = []): Deployment
    {
        return Deployment::create([
            'site_id' => $site->id,
            'type' => 'partial',
            'status' => 'building',
            'triggered_by' => $this->owner->id,
            'metadata' => $metadata,
        ]);
    }

    private function job(Deployment $deployment): PublishSiteJob
    {
        $job = new PublishSiteJob($deployment, 'partial');
        $job->deployment = $deployment; // handle() would set this; do it for direct calls

        return $job;
    }

    private function invoke(object $obj, string $method, array $args)
    {
        $m = new \ReflectionMethod($obj, $method);
        $m->setAccessible(true);

        return $m->invoke($obj, ...$args);
    }

    public function test_gate_is_off_by_default(): void
    {
        // Default config: parallel_posts is false.
        $site = $this->makeSite();
        $job = $this->job($this->deployment($site));

        config(['queue.default' => 'redis']);
        $this->assertFalse($this->invoke($job, 'shouldRenderPostsInParallel', [10_000, false]));
    }

    public function test_gate_rules(): void
    {
        $site = $this->makeSite();
        config(['publishing.parallel_posts' => true, 'publishing.parallel_chunk_size' => 400, 'queue.default' => 'redis']);

        $on = fn (Deployment $d, int $count, bool $proj) => $this->invoke($this->job($d), 'shouldRenderPostsInParallel', [$count, $proj]);

        // Above threshold, projection off, async, not-yet-rendered → parallel.
        $this->assertTrue($on($this->deployment($site), 401, false));
        // At/below threshold → serial.
        $this->assertFalse($on($this->deployment($site), 400, false));
        // Projection-on site → serial (must emit sidecars in one pass).
        $this->assertFalse($on($this->deployment($site), 401, true));
        // Already rendered (finalize re-run) → serial, so it can't loop.
        $this->assertFalse($on($this->deployment($site, ['parallel_rendered' => true]), 401, false));

        // Sync queue → serial (no workers to fan across).
        config(['queue.default' => 'sync']);
        $this->assertFalse($on($this->deployment($site), 401, false));
    }

    public function test_dispatch_fans_one_chunk_job_per_chunk(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $cat = Category::factory()->create(['site_id' => $site->id, 'slug' => 'news']);
        Post::factory()->published()->count(3)->create(['site_id' => $site->id, 'category_id' => $cat->id]);

        config(['publishing.parallel_posts' => true, 'publishing.parallel_chunk_size' => 1, 'queue.default' => 'redis']);

        $deployment = $this->deployment($site);
        $query = Post::where('site_id', $site->id)->where('status', 'published');
        $this->invoke($this->job($deployment), 'dispatchParallelPostBuild', [$query, 3]);

        // 3 posts, chunk size 1 → 3 chunk jobs in one batch.
        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->count() === 3
                && $batch->jobs->every(fn ($j) => $j instanceof BuildPostsChunkJob);
        });

        // Flag set so the finalize re-run takes the serial path.
        $this->assertTrue((bool) ($deployment->fresh()->metadata['parallel_rendered'] ?? false));
    }

    public function test_chunk_job_renders_its_posts_into_staging(): void
    {
        $site = $this->makeSite();
        $cat = Category::factory()->create(['site_id' => $site->id, 'slug' => 'news']);
        $posts = Post::factory()->published()->count(2)->create(['site_id' => $site->id, 'category_id' => $cat->id]);
        $deployment = $this->deployment($site);
        $staging = rtrim(config('publishing.staging_path'), '/') . "/{$deployment->id}";

        (new BuildPostsChunkJob($deployment->id, $this->tenant->id, $posts->pluck('id')->all(), 0))
            ->handle(app(BuildPageService::class));

        foreach ($posts as $post) {
            $path = LocalePaths::postPath($site, $post);
            $this->assertFileExists("{$staging}/{$path}", "post {$post->slug} should be rendered into staging");
            $this->assertDatabaseHas('page_versions', ['post_id' => $post->id]);
        }

        File::deleteDirectory($staging);
    }
}
