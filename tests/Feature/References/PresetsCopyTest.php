<?php

namespace Tests\Feature\References;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Asset;
use App\Models\BlockTemplate;
use App\Models\EntityReference;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/**
 * Regression pin for the presets-from-primitives principle: block templates
 * and presets instantiate by COPY. Instantiating one must NEVER create a live
 * edge to the template — only edges from the copied blocks' own reference
 * fields. If someone introduces template transclusion, this test fails.
 */
class PresetsCopyTest extends TestCase
{
    public function test_instantiating_a_block_template_creates_no_template_edges(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $asset = Asset::factory()->create(['site_id' => $site->id]);

        $template = new BlockTemplate([
            'site_id' => $site->id,
            'name' => 'Hero preset',
            'category' => 'hero',
            'blocks_data' => [
                ['type' => 'image', 'order' => 0, 'data' => ['asset_id' => $asset->id]],
            ],
            'is_system' => false,
        ]);
        // BlockTemplate assumes integer keys ($incrementing) — keep the uuid
        // locally, since $template->id would come back int-cast
        $templateId = (string) \Illuminate\Support\Str::uuid();
        $template->id = $templateId;
        $template->save();

        // Instantiation = client inserts a detached copy of blocks_data,
        // tagged with preset_id (a plain string, not a foreign key)
        $page = Page::factory()->published()->create(['site_id' => $site->id]);
        $copied = array_map(
            fn (array $block) => $block + ['preset_id' => $templateId],
            $template->blocks_data,
        );
        app(BlockService::class)->syncBlocks($page, $copied);

        // The copy's own references ARE tracked…
        $this->assertSame(1, EntityReference::forSource('page', $page->id)
            ->where('target_type', 'asset')->where('target_id', $asset->id)->count());

        // …but NOTHING references the template: not from this page, not at all
        $this->assertSame(0, EntityReference::where('target_type', 'block_template')->count());
        $this->assertSame(0, EntityReference::where('target_id', $templateId)->count());

        // And editing the template afterwards must not affect the page's edges
        $template->update(['blocks_data' => []]);
        $this->assertSame(1, EntityReference::forSource('page', $page->id)->count());
    }
}
