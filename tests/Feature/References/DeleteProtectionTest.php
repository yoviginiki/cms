<?php

namespace Tests\Feature\References;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Asset;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Deleting an entity with inbound references requires an explicit force flag;
 * the 409 response exposes usedOnCount + the referring sources.
 */
class DeleteProtectionTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function pageUsingAsset(Asset $asset): Page
    {
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'image', 'order' => 0, 'data' => ['asset_id' => $asset->id]],
        ]);

        return $page;
    }

    public function test_deleting_used_asset_returns_409_with_referring_pages(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);
        $page = $this->pageUsingAsset($asset);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/assets/{$asset->id}")
            ->assertStatus(409)
            ->assertJsonPath('usedOnCount', 1)
            ->assertJsonPath('sources.0.type', 'page')
            ->assertJsonPath('sources.0.title', $page->title);

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_force_deleting_used_asset_succeeds_and_flags_referrers(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);
        $page = $this->pageUsingAsset($asset);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/assets/{$asset->id}?force=1")
            ->assertStatus(204);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $this->assertTrue($page->fresh()->needs_republish);
    }

    public function test_deleting_unused_asset_needs_no_force(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/assets/{$asset->id}")
            ->assertStatus(204);
    }

    public function test_deleting_embedded_menu_returns_409_then_force_succeeds(): void
    {
        $menu = Menu::create(['site_id' => $this->site->id, 'name' => 'Nav', 'slug' => 'nav']);
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'menu', 'order' => 0, 'data' => ['menuId' => $menu->id]],
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/menus/{$menu->id}")
            ->assertStatus(409)
            ->assertJsonPath('usedOnCount', 1);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/menus/{$menu->id}?force=1")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertTrue($page->fresh()->needs_republish);
    }

    public function test_usage_endpoint_reports_used_on_count(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);
        $this->pageUsingAsset($asset);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/references/usage?target_type=asset&target_id={$asset->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.sources.0.kind', 'uses_asset');
    }
}
