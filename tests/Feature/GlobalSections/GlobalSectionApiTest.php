<?php

namespace Tests\Feature\GlobalSections;

use App\Domain\Blocks\Services\BlockService;
use App\Models\BlockTemplate;
use App\Models\GlobalSection;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Builder Experience P2 — Global Sections HTTP surface (mirrors SliderTest).
 */
class GlobalSectionApiTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
    }

    private function base(): string
    {
        return "/api/v1/sites/{$this->site->id}/global-sections";
    }

    public function test_create_seeds_an_empty_section(): void
    {
        $id = $this->actingAsOwner()->postJson($this->base(), ['name' => 'Footer'])->assertStatus(201)->json('data.id');
        $show = $this->actingAsOwner()->getJson("{$this->base()}/{$id}")->assertOk()->json('data');
        $this->assertSame('Footer', $show['section']['name']);
        $this->assertSame('section', $show['blocks'][0]['type']);
    }

    public function test_promote_from_library_creates_a_section(): void
    {
        $item = BlockTemplate::create([
            'site_id' => $this->site->id, 'name' => 'Band', 'category' => 'custom', 'kind' => 'section',
            'blocks_data' => [['type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [], 'children' => [
                ['type' => 'text', 'level' => 'module', 'order' => 0, 'data' => ['content' => 'Hi']],
            ]]],
        ]);

        $resp = $this->actingAsOwner()->postJson("{$this->base()}/promote", ['block_template_id' => $item->id])->assertStatus(201);
        $this->assertSame('Band', $resp->json('data.name'));
        $this->assertDatabaseHas('global_sections', ['id' => $resp->json('data.id'), 'name' => 'Band']);
    }

    public function test_edit_blocks_and_publish_flags_embedding_pages(): void
    {
        $id = $this->actingAsOwner()->postJson($this->base(), ['name' => 'CTA'])->json('data.id');
        $section = GlobalSection::find($id);

        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'global_ref', 'order' => 0, 'data' => ['sectionId' => $section->id]],
        ]);

        // index reports used_on
        $this->actingAsOwner()->getJson($this->base())->assertOk()->assertJsonPath('data.0.used_on', 1);

        $publish = $this->actingAsOwner()->postJson("{$this->base()}/{$id}/publish")->assertOk();
        $this->assertSame('published', $publish->json('data.status'));
        $this->assertSame(1, $publish->json('meta.stale.pages'));
        $this->assertTrue($page->fresh()->needs_republish);
    }

    public function test_delete_is_blocked_while_in_use_then_forced(): void
    {
        $id = $this->actingAsOwner()->postJson($this->base(), ['name' => 'Used'])->json('data.id');
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'global_ref', 'order' => 0, 'data' => ['sectionId' => $id]],
        ]);

        $this->actingAsOwner()->deleteJson("{$this->base()}/{$id}")->assertStatus(409);
        $this->actingAsOwner()->deleteJson("{$this->base()}/{$id}?force=1")->assertStatus(204);
        $this->assertSoftDeleted('global_sections', ['id' => $id]); // SoftDeletes, like Slider
        // and it no longer lists
        $this->actingAsOwner()->getJson($this->base())->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cross_site_section_is_404(): void
    {
        $id = $this->actingAsOwner()->postJson($this->base(), ['name' => 'Mine'])->json('data.id');
        $otherSite = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsOwner()->getJson("/api/v1/sites/{$otherSite->id}/global-sections/{$id}")->assertStatus(404);
    }
}
