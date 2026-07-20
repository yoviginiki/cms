<?php

namespace Tests\Feature\References;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Collections\Services\RecordService;
use App\Domain\References\Services\StalenessResolver;
use App\Models\ActivityLog;
use App\Models\Deployment;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\Feature\Collections\BuildsCollections;
use Tests\TestCase;

/**
 * Phase 4.1b — auto-republish toggle (per-site, DEFAULT OFF).
 * ON: flagging pages stale queues a stale batch that builds, promotes, clears
 * flags, and logs one entry per page. OFF: flags stay, nothing is queued.
 */
class AutoRepublishTest extends TestCase
{
    use BuildsCollections;

    private string $publishRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        // isolate deploy targets under the framework testing dir
        $this->publishRoot = storage_path('framework/testing/sites-' . uniqid());
        config(['publishing.public_path' => $this->publishRoot]);
        // AppServiceProvider forces queue.default=database when Redis is off;
        // run the auto batch inline for this test
        config(['queue.default' => 'sync']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publishRoot);
        parent::tearDown();
    }

    private function makeSite(bool $autoRepublish): Site
    {
        return Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'settings' => [
                'auto_publish' => false,
                'auto_republish_stale' => $autoRepublish,
                'deploy_method' => 'local',
            ],
        ]);
    }

    private function makePageEmbeddingMenu(Site $site, Menu $menu): Page
    {
        $page = Page::factory()->published()->create(['site_id' => $site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'menu', 'order' => 0, 'data' => ['menuId' => $menu->id]],
            ['type' => 'paragraph', 'order' => 1, 'data' => ['content' => 'auto-republish probe']],
        ]);

        return $page;
    }

    public function test_toggle_on_rebuilds_promotes_clears_flags_and_logs_per_page(): void
    {
        $site = $this->makeSite(autoRepublish: true);
        $menu = Menu::create(['site_id' => $site->id, 'name' => 'Embedded', 'slug' => 'embedded']);
        $page = $this->makePageEmbeddingMenu($site, $menu);

        // QUEUE_CONNECTION=sync in tests: the queued batch runs inline here
        app(StalenessResolver::class)->markStale($site, 'menu', $menu->id, "Menu 'Embedded' updated");

        $deployment = Deployment::where('site_id', $site->id)->where('type', 'stale_batch')->first();
        $this->assertNotNull($deployment, 'auto batch was queued');
        $this->assertSame('live', $deployment->status, 'auto batch promoted without manual action');
        $this->assertTrue($deployment->metadata['auto_promote']);

        // dependent's markup landed in the live docroot
        $this->assertFileExists("{$this->publishRoot}/{$site->slug}/{$page->slug}/index.html");
        $this->assertStringContainsString(
            'auto-republish probe',
            file_get_contents("{$this->publishRoot}/{$site->slug}/{$page->slug}/index.html"),
        );

        // flags cleared, one visible log entry per page
        $this->assertFalse($page->fresh()->needs_republish);
        $this->assertSame(1, ActivityLog::where('action', 'page.auto_republished')
            ->where('subject_id', $page->id)->count());
        $this->assertSame(1, ActivityLog::where('action', 'stale.auto_republish_queued')->count());
    }

    public function test_record_only_staleness_queues_and_builds_record_pages(): void
    {
        $site = $this->makeSite(autoRepublish: true);
        $authors = $this->createAuthorsCollection($site);

        // A record save flags only the record + its collection stale — no page
        // or post carries a flag. Before the fix, maybeQueue() ignored records
        // and silently skipped the batch.
        $record = app(RecordService::class)->save($authors, $site, null, [
            'data' => ['name' => 'Ursula K. Le Guin'],
            'status' => 'published',
        ]);

        $deployment = Deployment::where('site_id', $site->id)->where('type', 'stale_batch')->first();
        $this->assertNotNull($deployment, 'record-only staleness queued an auto batch');
        $this->assertSame('live', $deployment->status, 'auto batch promoted');
        $this->assertContains($record->id, $deployment->metadata['targets']['records'] ?? []);

        $detail = "{$this->publishRoot}/{$site->slug}/authors/{$record->slug}/index.html";
        $this->assertFileExists($detail);
        $this->assertStringContainsString('Ursula', file_get_contents($detail));
        $this->assertFileExists("{$this->publishRoot}/{$site->slug}/authors/index.json");

        $this->assertFalse($record->fresh()->needs_republish, 'record flag cleared by the batch');
    }

    public function test_toggle_off_default_flags_only_and_queues_nothing(): void
    {
        $site = $this->makeSite(autoRepublish: false);
        $menu = Menu::create(['site_id' => $site->id, 'name' => 'Embedded', 'slug' => 'embedded']);
        $page = $this->makePageEmbeddingMenu($site, $menu);

        app(StalenessResolver::class)->markStale($site, 'menu', $menu->id, "Menu 'Embedded' updated");

        $this->assertTrue($page->fresh()->needs_republish, 'flag-and-confirm behavior preserved');
        $this->assertSame(0, Deployment::where('site_id', $site->id)->where('type', 'stale_batch')->count());
    }

    public function test_toggle_on_but_full_auto_publish_on_defers_to_full_rebuild(): void
    {
        $site = Site::factory()->create([
            'tenant_id' => $this->tenant->id,
            'settings' => ['auto_publish' => true, 'auto_republish_stale' => true],
        ]);
        $menu = Menu::create(['site_id' => $site->id, 'name' => 'Embedded', 'slug' => 'embedded']);
        $this->makePageEmbeddingMenu($site, $menu);

        app(StalenessResolver::class)->markStale($site, 'menu', $menu->id, 'updated');

        // no batch: the full auto-publish covers dependents, no double build
        $this->assertSame(0, Deployment::where('site_id', $site->id)->where('type', 'stale_batch')->count());
    }
}
