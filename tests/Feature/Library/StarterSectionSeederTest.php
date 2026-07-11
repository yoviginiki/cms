<?php

namespace Tests\Feature\Library;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Library\Services\LibraryItemSanitizer;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Page;
use App\Models\Site;
use Database\Seeders\StarterSectionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Starter section packs (Builder P5 → Theme track). The seeder ships SYSTEM
 * Library sections (site_id NULL, is_system true) every tenant can read + insert
 * but never modify. Verifies the trees are valid, visible via the API, and
 * render (token-styled html-embed survives to published output).
 */
class StarterSectionSeederTest extends TestCase
{
    private function systemItems()
    {
        return DB::table('block_templates')->whereNull('site_id')->where('is_system', true)->get();
    }

    public function test_seeds_system_sections(): void
    {
        $this->seed(StarterSectionSeeder::class);

        $items = $this->systemItems();
        $this->assertGreaterThanOrEqual(8, $items->count());
        foreach ($items as $it) {
            $this->assertNull($it->site_id);
            $this->assertEquals(1, $it->is_system);
            $this->assertEquals('section', $it->kind);
            $this->assertStringContainsString('starter', $it->tags); // json array text
        }
    }

    public function test_is_idempotent(): void
    {
        $this->seed(StarterSectionSeeder::class);
        $first = $this->systemItems()->count();
        $this->seed(StarterSectionSeeder::class);
        $this->assertSame($first, $this->systemItems()->count());
    }

    public function test_every_section_tree_is_valid(): void
    {
        $this->seed(StarterSectionSeeder::class);
        $sanitizer = app(LibraryItemSanitizer::class);

        foreach ($this->systemItems() as $it) {
            $tree = json_decode($it->blocks_data, true);
            $this->assertIsArray($tree);
            // known block types + within node/depth bounds → no throw
            $clean = $sanitizer->sanitizeTree($tree);
            $this->assertNotEmpty($clean);
            $this->assertSame('section', $tree[0]['level']); // a section item
        }
    }

    public function test_tenant_can_read_but_not_own_system_items(): void
    {
        $this->seed(StarterSectionSeeder::class);
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $resp = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$site->id}/block-templates?kind=section")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('name');
        $this->assertContains('Hero — Centered', $names);
        // system items surface to the tenant but are owned by nobody
        $hero = collect($resp->json('data'))->firstWhere('name', 'Hero — Centered');
        $this->assertTrue((bool) $hero['is_system']);
    }

    public function test_a_seeded_section_publishes_with_tokens(): void
    {
        $this->seed(StarterSectionSeeder::class);
        $hero = $this->systemItems()->firstWhere('slug', 'starter-hero-centered');
        $tree = json_decode($hero->blocks_data, true);

        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);
        app(BlockService::class)->syncBlocks($page, $tree);

        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);
        // raw html-embed reaches published output, still token-styled
        $this->assertStringContainsString('Build something worth keeping.', $html);
        $this->assertStringContainsString('var(--color-heading)', $html);
    }
}
