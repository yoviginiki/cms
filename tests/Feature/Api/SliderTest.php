<?php

namespace Tests\Feature\Api;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Page;
use App\Models\Site;
use App\Models\Slider;
use Tests\TestCase;

class SliderTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function createSlider(string $name = 'Hero slider'): Slider
    {
        $response = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/sliders", ['name' => $name])
            ->assertStatus(201);

        return Slider::find($response->json('data.id'));
    }

    public function test_create_seeds_root_block_and_empty_slide(): void
    {
        $slider = $this->createSlider();

        $this->assertNotNull($slider->root_block_id);
        $show = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}")
            ->assertStatus(200)
            ->json('data');
        $this->assertSame('slider', $show['blocks'][0]['type']);
        $this->assertSame('slide', $show['blocks'][0]['children'][0]['type']);
    }

    public function test_sync_blocks_and_publish_flags_embedding_pages(): void
    {
        $slider = $this->createSlider();

        // page embeds it
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'slider_ref', 'order' => 0, 'data' => ['sliderId' => $slider->id]],
        ]);

        // edit the slider tree (add a text layer) then publish
        $tree = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}")->json('data.blocks');
        $tree[0]['children'][0]['children'][] = [
            'type' => 'text', 'level' => 'module', 'order' => 0,
            'data' => ['content' => 'Layer', 'layout' => ['x' => '8%', 'y' => '30%'],
                'animation' => ['in' => ['preset' => 'fadeUp', 'duration' => 0.6]]],
        ];
        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}/blocks", ['blocks' => $tree])
            ->assertStatus(200);

        $publish = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}/publish")
            ->assertStatus(200);

        $this->assertSame('published', $publish->json('data.status'));
        $this->assertSame(1, $publish->json('meta.stale.pages'));
        $this->assertTrue($page->fresh()->needs_republish);
    }

    public function test_index_reports_used_on_counts(): void
    {
        $slider = $this->createSlider();
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'slider_ref', 'order' => 0, 'data' => ['sliderId' => $slider->id]],
        ]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/sliders")
            ->assertStatus(200)
            ->assertJsonPath('data.0.used_on', 1);
    }

    public function test_duplicate_copies_the_tree_with_fresh_ids(): void
    {
        $slider = $this->createSlider();
        $copyId = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}/duplicate")
            ->assertStatus(201)
            ->json('data.id');

        $copyBlocks = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/sliders/{$copyId}")->json('data.blocks');
        $origBlocks = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}")->json('data.blocks');

        $this->assertSame('slider', $copyBlocks[0]['type']);
        $this->assertNotSame($origBlocks[0]['id'], $copyBlocks[0]['id']);
    }

    public function test_delete_protection_requires_force_when_embedded(): void
    {
        $slider = $this->createSlider();
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'slider_ref', 'order' => 0, 'data' => ['sliderId' => $slider->id]],
        ]);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}")
            ->assertStatus(409)
            ->assertJsonPath('usedOnCount', 1);

        $this->actingAsOwner()
            ->deleteJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}?force=1")
            ->assertStatus(204);

        $this->assertSoftDeleted('sliders', ['id' => $slider->id]);
        $this->assertTrue($page->fresh()->needs_republish);
        // block tree cleaned up
        $this->assertSame(0, \App\Models\Block::where('blockable_type', 'slider')
            ->where('blockable_id', $slider->id)->count());
    }

    public function test_page_preview_endpoint_inlines_the_published_slider(): void
    {
        // Phase 5.3 parity: the EXISTING preview endpoint renders the slider
        // through the same Blade path as staged output — no second renderer
        $slider = $this->createSlider();
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}/publish")
            ->assertStatus(200);

        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'slider_ref', 'order' => 0, 'data' => ['sliderId' => $slider->id]],
        ]);

        $html = $this->actingAsOwner()
            ->get("/api/v1/sites/{$this->site->id}/pages/{$page->id}/preview")
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('data-slider-id=', $html);
        $this->assertStringContainsString('data-slider-config', $html);
        $this->assertStringContainsString('aria-roledescription="carousel"', $html);
        // self-hosted vendors (tenant CSPs block third-party CDNs)
        $this->assertStringContainsString('/assets/vendor/swiper-bundle-11.min.js', $html);
        $this->assertStringContainsString('/assets/vendor/gsap-3.15.0.min.js', $html);
    }

    public function test_responsive_layer_overrides_reach_published_output(): void
    {
        $slider = $this->createSlider();
        $tree = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}")->json('data.blocks');
        $tree[0]['children'][0]['children'][] = [
            'type' => 'text', 'level' => 'module', 'order' => 0,
            'data' => [
                'content' => 'Responsive layer',
                'layout' => ['x' => '8%', 'y' => '30%'],
                'responsiveLayout' => ['mobile' => ['x' => '4%', 'hidden' => false, 'widthPct' => 90]],
            ],
        ];
        $this->actingAsOwner()
            ->putJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}/blocks", ['blocks' => $tree])
            ->assertStatus(200);
        $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/sliders/{$slider->id}/publish")->assertStatus(200);

        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'slider_ref', 'order' => 0, 'data' => ['sliderId' => $slider->id]],
        ]);

        $html = $this->actingAsOwner()
            ->get("/api/v1/sites/{$this->site->id}/pages/{$page->id}/preview")->getContent();

        $this->assertStringContainsString('@media (max-width:767px)', $html);
        $this->assertStringContainsString('left:4% !important', $html);
        $this->assertStringContainsString('width:90% !important', $html);
    }

    public function test_cross_site_slider_is_not_accessible(): void
    {
        $slider = $this->createSlider();
        $otherSite = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$otherSite->id}/sliders/{$slider->id}")
            ->assertStatus(404);
    }
}
