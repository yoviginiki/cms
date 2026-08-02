<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Phase 6.2 — publish scenarios for the Content Projection Layer.
 */
class ProjectionScenariosTest extends TestCase
{
    private function publicSite(): array
    {
        config(['queue.default' => 'sync']);
        $this->setTenantScope($this->owner);
        $site = $this->createSiteWithPages(0);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        $site->update(['settings' => array_merge($site->settings ?? [], [
            'homepage_id' => $page->id,
            'default_locale' => 'en',
            'crawler_policy' => ['projection_access' => 'public'],
        ])]);

        return [$site->fresh(), $page->fresh()];
    }

    public function test_rollback_restores_projection_in_sync_with_html(): void
    {
        [$site, $page] = $this->publicSite();
        $image = Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'image', 'order' => 0,
            'data' => ['url' => 'https://cdn/x.jpg', 'alt' => 'CAT ONE'],
        ]);
        $orchestrator = app(PublishOrchestrator::class);
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        $v1 = $orchestrator->publish($site->fresh(), $this->owner, 'full');
        $sidecarV1 = json_decode(file_get_contents("{$docroot}/index.json"), true);
        $this->assertSame('CAT ONE', collect($sidecarV1['schema_org']['@graph'])->firstWhere('@type', 'ImageObject')['name']);

        // v2 changes the image alt.
        $image->update(['data' => ['url' => 'https://cdn/x.jpg', 'alt' => 'CAT TWO']]);
        $orchestrator->publish($site->fresh(), $this->owner, 'full');
        $sidecarV2 = json_decode(file_get_contents("{$docroot}/index.json"), true);
        $this->assertSame('CAT TWO', collect($sidecarV2['schema_org']['@graph'])->firstWhere('@type', 'ImageObject')['name']);

        // Rollback to v1 → the sidecar rides the whole-tree swap back with the HTML.
        $orchestrator->rollback($site->fresh(), $v1, $this->owner);
        $sidecarRb = json_decode(file_get_contents("{$docroot}/index.json"), true);
        $this->assertSame('CAT ONE', collect($sidecarRb['schema_org']['@graph'])->firstWhere('@type', 'ImageObject')['name']);
        $this->assertStringContainsString('CAT ONE', file_get_contents("{$docroot}/index.html"));
    }

    public function test_page_with_only_unmarked_blocks_publishes_valid_empty_projection(): void
    {
        [$site, $page] = $this->publicSite();
        // A lone unmarked container block — nothing opts into the projection.
        Block::create([
            'blockable_type' => $page->getMorphClass(), 'blockable_id' => $page->id,
            'parent_block_id' => null, 'type' => 'divider', 'order' => 0,
            'data' => [],
        ]);

        app(PublishOrchestrator::class)->publish($site, $this->owner, 'full');
        $docroot = config('publishing.public_path') . '/' . $site->slug;

        // Publishes normally, sidecar present and a valid empty projection.
        $this->assertFileExists("{$docroot}/index.html");
        $sidecar = json_decode(file_get_contents("{$docroot}/index.json"), true);
        $this->assertIsArray($sidecar);
        $this->assertSame([], $sidecar['schema_org']['@graph']);
        $this->assertSame([], $sidecar['structure']);
    }
}
