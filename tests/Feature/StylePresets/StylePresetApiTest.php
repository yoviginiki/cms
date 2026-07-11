<?php

namespace Tests\Feature\StylePresets;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Page;
use App\Models\Site;
use App\Models\StylePreset;
use Tests\TestCase;

/**
 * P3 preset API: CRUD, the block→preset 'uses' edge, and the edit→stale cascade.
 */
class StylePresetApiTest extends TestCase
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
        return "/api/v1/sites/{$this->site->id}/style-presets";
    }

    public function test_create_and_list_by_block_type(): void
    {
        $this->actingAsOwner()->postJson($this->base(), [
            'name' => 'Accent CTA', 'block_type' => 'button', 'kind' => 'element',
            'style' => ['visual' => ['backgroundColor' => '$color.accent']], 'is_default' => true,
        ])->assertStatus(201)->assertJsonPath('data.slug', 'accent-cta');

        // wildcard presets show for any block_type filter
        $this->actingAsOwner()->postJson($this->base(), ['name' => 'Any', 'block_type' => '*', 'style' => ['spacing' => ['gap' => '8px']]]);

        $this->actingAsOwner()->getJson($this->base() . '?block_type=button')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsOwner()->getJson($this->base() . '?block_type=heading')->assertOk()->assertJsonCount(1, 'data'); // just the '*'
    }

    public function test_block_linking_a_preset_records_a_uses_edge(): void
    {
        $preset = StylePreset::create(['site_id' => $this->site->id, 'block_type' => 'text', 'kind' => 'element', 'name' => 'P', 'style' => []]);
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'text', 'order' => 0, 'data' => ['content' => 'Hi', '__stylePreset' => $preset->id]],
        ]);

        $this->assertDatabaseHas('entity_references', [
            'source_type' => 'page', 'source_id' => $page->id,
            'target_type' => 'style_preset', 'target_id' => $preset->id, 'kind' => 'uses',
        ]);
    }

    public function test_editing_a_preset_flags_linked_pages_stale(): void
    {
        $preset = StylePreset::create(['site_id' => $this->site->id, 'block_type' => 'text', 'kind' => 'element', 'name' => 'P', 'style' => []]);
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'text', 'order' => 0, 'data' => ['content' => 'Hi', '__stylePreset' => $preset->id]],
        ]);
        $page->update(['needs_republish' => false]);

        $resp = $this->actingAsOwner()->patchJson("{$this->base()}/{$preset->id}", [
            'style' => ['visual' => ['backgroundColor' => '$color.brand']],
        ])->assertOk();

        $this->assertGreaterThanOrEqual(1, $resp->json('meta.stale.pages'));
        $this->assertTrue($page->fresh()->needs_republish);
    }

    public function test_option_group_presets_also_cascade(): void
    {
        $group = StylePreset::create(['site_id' => $this->site->id, 'block_type' => 'text', 'kind' => 'group', 'group' => 'spacing', 'name' => 'Tight', 'style' => []]);
        $page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($page, [
            ['type' => 'text', 'order' => 0, 'data' => ['content' => 'Hi', '__presetGroups' => [$group->id]]],
        ]);

        $this->assertDatabaseHas('entity_references', [
            'source_type' => 'page', 'target_type' => 'style_preset', 'target_id' => $group->id, 'kind' => 'uses',
        ]);
    }

    public function test_export_then_import_roundtrips(): void
    {
        StylePreset::create(['site_id' => $this->site->id, 'block_type' => 'text', 'kind' => 'element', 'name' => 'Ex', 'style' => ['visual' => ['backgroundColor' => '$color.accent']]]);

        $doc = $this->actingAsOwner()->getJson("{$this->base()}/export")->assertOk()->json('data');
        $this->assertSame(1, $doc['version']);

        $otherSite = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAsOwner()->postJson("/api/v1/sites/{$otherSite->id}/style-presets/import", ['presets' => $doc['presets']])
            ->assertStatus(201)->assertJsonPath('data.imported', 1);
        $this->assertDatabaseHas('style_presets', ['site_id' => $otherSite->id, 'name' => 'Ex']);
    }

    public function test_system_presets_are_read_only(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE style_presets DISABLE ROW LEVEL SECURITY');
        $sys = new StylePreset();
        $sys->forceFill(['site_id' => null, 'block_type' => 'text', 'kind' => 'element', 'name' => 'Sys', 'style' => [], 'is_system' => true]);
        $sys->id = \Illuminate\Support\Str::uuid()->toString();
        $sys->save();
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE style_presets ENABLE ROW LEVEL SECURITY');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE style_presets FORCE ROW LEVEL SECURITY');

        $this->actingAsOwner()->patchJson("{$this->base()}/{$sys->id}", ['name' => 'X'])->assertStatus(403);
        $this->actingAsOwner()->deleteJson("{$this->base()}/{$sys->id}")->assertStatus(403);
    }

    public function test_adopt_clones_a_system_preset_into_an_editable_site_default(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE style_presets DISABLE ROW LEVEL SECURITY');
        $sys = new StylePreset();
        $sys->forceFill([
            'site_id' => null, 'block_type' => 'button', 'kind' => 'element', 'name' => 'Primary',
            'style' => ['visual' => ['backgroundColor' => '$color.primary']], 'is_system' => true,
        ]);
        $sys->id = \Illuminate\Support\Str::uuid()->toString();
        $sys->save();
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE style_presets ENABLE ROW LEVEL SECURITY');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE style_presets FORCE ROW LEVEL SECURITY');

        $resp = $this->actingAsOwner()
            ->postJson("{$this->base()}/{$sys->id}/adopt")
            ->assertStatus(201);

        // a fresh site-owned, editable copy with the same style
        $resp->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.site_id', $this->site->id)
            ->assertJsonPath('data.block_type', 'button')
            ->assertJsonPath('data.style.visual.backgroundColor', '$color.primary');
        $copyId = $resp->json('data.id');
        $this->assertNotSame($sys->id, $copyId);

        // the copy (unlike the system original) can be starred as the site default
        $this->actingAsOwner()
            ->patchJson("{$this->base()}/{$copyId}", ['is_default' => true])
            ->assertOk()->assertJsonPath('data.is_default', true);

        // the system original is untouched
        $this->assertDatabaseHas('style_presets', ['id' => $sys->id, 'is_system' => true]);
    }
}
