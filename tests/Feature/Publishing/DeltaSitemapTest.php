<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Jobs\RepublishStaleJob;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Deployment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * FIX-B7a — a delta / stale republish must regenerate sitemap.xml and feed.xml
 * so they don't point at old/dead URLs until the next full publish.
 */
class DeltaSitemapTest extends TestCase
{
    public function test_delta_republish_regenerates_sitemap_and_feed(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = Page::factory()->published()->create(['site_id' => $site->id]);
        Post::factory()->published()->create(['site_id' => $site->id, 'category_id' => null]);

        $deployment = Deployment::create([
            'site_id' => $site->id,
            'type' => 'stale_batch',
            'status' => 'queued',
            'triggered_by' => $this->owner->id,
            'metadata' => ['targets' => ['pages' => [$page->id], 'posts' => []]],
        ]);

        (new RepublishStaleJob($deployment))->handle(app(BuildPageService::class));

        $staging = storage_path("app/builds/{$deployment->id}");
        $this->assertFileExists("{$staging}/sitemap.xml");
        $this->assertFileExists("{$staging}/feed.xml");
        $this->assertStringContainsString('<urlset', file_get_contents("{$staging}/sitemap.xml"));

        File::deleteDirectory($staging);
    }
}
