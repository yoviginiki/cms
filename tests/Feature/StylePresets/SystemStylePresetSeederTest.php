<?php

namespace Tests\Feature\StylePresets;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Page;
use App\Models\Site;
use Database\Seeders\SystemStylePresetSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * System style presets (Builder P3 → Theme track). Shared, on-brand,
 * token-based presets (site_id NULL, is_system true) every tenant can apply but
 * never edit. Verifies seeding, idempotency, API visibility/immutability, and
 * that a linked preset resolves its $tokens to var(--…) at publish.
 */
class SystemStylePresetSeederTest extends TestCase
{
    private function systemPresets()
    {
        return DB::table('style_presets')->whereNull('site_id')->where('is_system', true)->get();
    }

    public function test_seeds_system_presets(): void
    {
        $this->seed(SystemStylePresetSeeder::class);

        $items = $this->systemPresets();
        $this->assertGreaterThanOrEqual(11, $items->count());
        foreach ($items as $p) {
            $this->assertNull($p->site_id);
            $this->assertEquals(1, $p->is_system);
            $this->assertContains($p->kind, ['element', 'group']);
            $this->assertEquals(0, $p->is_default); // shared library, not a silent global default
        }
    }

    public function test_is_idempotent(): void
    {
        $this->seed(SystemStylePresetSeeder::class);
        $first = $this->systemPresets()->count();
        $this->seed(SystemStylePresetSeeder::class);
        $this->assertSame($first, $this->systemPresets()->count());
    }

    public function test_tenant_lists_system_presets_but_cannot_edit_them(): void
    {
        $this->seed(SystemStylePresetSeeder::class);
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $resp = $this->actingAsOwner()
            ->getJson("/api/v1/sites/{$site->id}/style-presets?block_type=button")
            ->assertOk();
        $names = collect($resp->json('data'))->pluck('name');
        $this->assertContains('Primary', $names);

        // system presets are immutable through the app
        $primary = $this->systemPresets()->firstWhere('slug', 'sys-button-primary');
        $this->actingAsOwner()
            ->patchJson("/api/v1/sites/{$site->id}/style-presets/{$primary->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_linked_preset_resolves_tokens_at_publish(): void
    {
        $this->seed(SystemStylePresetSeeder::class);
        $primary = $this->systemPresets()->firstWhere('slug', 'sys-button-primary');

        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $page = Page::factory()->create(['site_id' => $site->id, 'status' => 'published']);

        app(BlockService::class)->syncBlocks($page, [[
            'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [],
            'children' => [[
                'type' => 'row', 'level' => 'row', 'order' => 0, 'data' => ['layout' => '1'],
                'children' => [[
                    'type' => 'column', 'level' => 'column', 'order' => 0, 'data' => [],
                    'children' => [[
                        'type' => 'button', 'level' => 'module', 'order' => 0,
                        // element preset link lives in data.__stylePreset (not the column)
                        'data' => ['text' => 'Go', 'url' => '#', '__stylePreset' => $primary->id],
                        'children' => [],
                    ]],
                ]],
            ]],
        ]]);

        $html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);

        // $color.primary → var(--color-primary), and the label preset applied
        $this->assertStringContainsString('var(--color-primary)', $html);
        $this->assertStringContainsString('text-transform:uppercase', $html);
    }
}
