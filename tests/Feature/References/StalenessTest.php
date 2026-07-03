<?php

namespace Tests\Feature\References;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\References\Services\ReferenceRecorder;
use App\Domain\References\Services\StalenessResolver;
use App\Models\Asset;
use App\Models\EntityReference;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

class StalenessTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function resolver(): StalenessResolver
    {
        return app(StalenessResolver::class);
    }

    public function test_transitive_staleness_asset_to_slider_to_page(): void
    {
        // Future slider entity, modeled generically: page embeds slider,
        // slider uses asset — an asset change must reach the page through
        // the intermediate node.
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);
        $sliderId = (string) \Illuminate\Support\Str::uuid();

        EntityReference::create([
            'site_id' => $this->site->id,
            'source_type' => 'page', 'source_id' => $page->id,
            'target_type' => 'slider', 'target_id' => $sliderId, 'kind' => 'embeds',
        ]);
        EntityReference::create([
            'site_id' => $this->site->id,
            'source_type' => 'slider', 'source_id' => $sliderId,
            'target_type' => 'asset', 'target_id' => $asset->id, 'kind' => 'uses_asset',
        ]);

        $result = $this->resolver()->markStale($this->site, 'asset', $asset->id, 'Asset replaced');

        $this->assertSame(1, $result['pages']);
        $page->refresh();
        $this->assertTrue($page->needs_republish);
        $this->assertSame('Asset replaced', $page->needs_republish_reason);
    }

    public function test_cycle_in_graph_does_not_loop_forever(): void
    {
        $a = (string) \Illuminate\Support\Str::uuid();
        $b = (string) \Illuminate\Support\Str::uuid();
        // a embeds b, b embeds a — visited set must break the cycle
        EntityReference::create(['site_id' => $this->site->id, 'source_type' => 'slider', 'source_id' => $a, 'target_type' => 'slider', 'target_id' => $b, 'kind' => 'embeds']);
        EntityReference::create(['site_id' => $this->site->id, 'source_type' => 'slider', 'source_id' => $b, 'target_type' => 'slider', 'target_id' => $a, 'kind' => 'embeds']);

        $result = $this->resolver()->markStale($this->site, 'slider', $a, 'cycle test');

        $this->assertSame(['pages' => 0, 'posts' => 0, 'site_wide' => false], $result);
    }

    public function test_site_scope_change_sets_one_site_flag_not_per_page_rows(): void
    {
        Page::factory()->published()->count(3)->create(['site_id' => $this->site->id]);
        $menu = Menu::create(['site_id' => $this->site->id, 'name' => 'Header', 'slug' => 'header', 'location' => 'header']);
        app(ReferenceRecorder::class)->recomputeSiteScope($this->site);

        $result = $this->resolver()->markStale($this->site, 'menu', $menu->id, "Menu 'Header' updated");

        $this->assertTrue($result['site_wide']);
        $this->site->refresh();
        $this->assertTrue($this->site->settings['stale']['flag']);
        $this->assertSame("Menu 'Header' updated", $this->site->settings['stale']['reason']);
        // Lazy expansion: NO per-page rows were written
        $this->assertSame(0, Page::where('site_id', $this->site->id)->where('needs_republish', true)->count());
    }

    public function test_content_change_does_not_stale_pages_that_merely_link_to_it(): void
    {
        $target = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'about']);
        $referrer = Page::factory()->published()->create(['site_id' => $this->site->id]);
        EntityReference::create([
            'site_id' => $this->site->id,
            'source_type' => 'page', 'source_id' => $referrer->id,
            'target_type' => 'page', 'target_id' => $target->id, 'kind' => 'links',
        ]);

        // Content edit on the target: the link's href is unchanged — no staleness
        $result = $this->resolver()->markStale($this->site, 'page', $target->id, 'content edited');
        $this->assertSame(0, $result['pages']);

        // Slug change: the referrer now holds a dead URL — flagged
        $result = $this->resolver()->markStaleForLinkTargets($this->site, 'page', $target->id, 'renamed');
        $this->assertSame(1, $result['pages']);
        $this->assertTrue($referrer->fresh()->needs_republish);
    }

    public function test_slug_change_via_api_flags_referring_pages(): void
    {
        $target = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'services']);
        $referrer = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'home2']);
        app(BlockService::class)->syncBlocks($referrer, [
            ['type' => 'rich-text', 'order' => 0, 'data' => ['content' => '<a href="/services">See services</a>']],
        ]);

        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/pages/{$target->id}", ['slug' => 'our-services'])
            ->assertStatus(200);

        $referrer->refresh();
        $this->assertTrue($referrer->needs_republish);
        $this->assertStringContainsString('/services → /our-services', $referrer->needs_republish_reason);
    }

    public function test_post_publish_flags_unfiltered_listing_pages(): void
    {
        $listingPage = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($listingPage, [
            ['type' => 'latestposts', 'order' => 0, 'data' => []], // no category filter → wildcard
        ]);

        $post = Post::factory()->published()->create(['site_id' => $this->site->id]);
        $this->resolver()->resolveForPostChange($this->site, $post, "New post '{$post->title}' published");

        $this->assertTrue($listingPage->fresh()->needs_republish);
    }

    public function test_clear_for_site_removes_all_flags(): void
    {
        $page = Page::factory()->published()->create(['site_id' => $this->site->id, 'needs_republish' => true, 'needs_republish_reason' => 'x']);
        $this->resolver()->markSiteStale($this->site, 'theme changed');

        $this->resolver()->clearForSite($this->site);

        $this->assertFalse($page->fresh()->needs_republish);
        $this->assertArrayNotHasKey('stale', $this->site->fresh()->settings ?? []);
    }
}
